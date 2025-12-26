<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Jobs;

use Cline\Webhook\Server\Contracts\BackoffStrategy;
use Cline\Webhook\Server\Contracts\Signer;
use Cline\Webhook\Server\Events\DispatchingWebhookCallEvent;
use Cline\Webhook\Server\Events\FinalWebhookCallFailedEvent;
use Cline\Webhook\Server\Events\WebhookCallFailedEvent;
use Cline\Webhook\Server\Events\WebhookCallSucceededEvent;
use Cline\Webhook\Server\Exceptions\MaxRetriesExceededException;
use Cline\Webhook\Server\Exceptions\WebhookCallException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

use const JSON_THROW_ON_ERROR;

use function array_merge;
use function event;
use function json_encode;
use function throw_if;

/**
 * Queued job for delivering webhooks with automatic retry and backoff logic.
 *
 * Handles the complete webhook delivery lifecycle including signature generation,
 * HTTP request dispatch, response validation, and retry orchestration. Failures
 * are automatically retried according to the configured backoff strategy until
 * max attempts are reached. Events are fired at each stage for observability.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CallWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new webhook call job.
     *
     * @param string                $webhookId               Unique identifier for this webhook instance, used in signatures and tracking
     * @param string                $url                     Target URL where the webhook will be delivered
     * @param string                $httpVerb                HTTP method to use (typically POST, but configurable for other methods)
     * @param array<string, mixed>  $payload                 Webhook payload data that will be JSON-encoded and sent in the request body
     * @param array<string, string> $headers                 Additional custom HTTP headers to include in the request beyond standard webhook headers
     * @param array<string, mixed>  $meta                    Optional metadata for application use, not sent with the webhook but available for tracking
     * @param array<string>         $tags                    Queue tags for Laravel Horizon filtering and monitoring
     * @param Signer                $signer                  Signature generator instance that creates cryptographic signatures for webhook verification
     * @param int                   $timestamp               Unix timestamp when the webhook was created, included in signature to prevent replay attacks
     * @param int                   $tries                   Maximum number of delivery attempts including initial try and retries
     * @param BackoffStrategy       $backoffStrategy         Strategy for calculating delay between retry attempts
     * @param int                   $timeoutInSeconds        HTTP request timeout in seconds before considering the request failed
     * @param bool                  $verifySsl               Whether to verify SSL certificates on HTTPS requests (disable only for testing)
     * @param bool                  $throwExceptionOnFailure Whether to throw MaxRetriesExceededException when all attempts fail (false for silent failure)
     */
    public function __construct(
        private readonly string $webhookId,
        private readonly string $url,
        private readonly string $httpVerb,
        private readonly array $payload,
        private readonly array $headers,
        /**
         * @phpstan-ignore property.onlyWritten
         */
        private readonly array $meta,
        private readonly array $tags,
        private readonly Signer $signer,
        private readonly int $timestamp,
        public readonly int $tries,
        private readonly BackoffStrategy $backoffStrategy,
        private readonly int $timeoutInSeconds,
        private readonly bool $verifySsl,
        private readonly bool $throwExceptionOnFailure,
    ) {}

    /**
     * Get queue tags for Laravel Horizon filtering and monitoring.
     *
     * Combines custom tags with standard webhook tags to enable filtering
     * by webhook type or specific webhook ID in the Horizon dashboard.
     *
     * @return array<string> Array of tags including 'webhook' and 'webhook:{id}' plus any custom tags
     */
    public function tags(): array
    {
        return array_merge($this->tags, ['webhook', 'webhook:'.$this->webhookId]);
    }

    /**
     * Calculate delay in seconds before the next retry attempt.
     *
     * Called by Laravel's queue system to determine how long to wait
     * before retrying a failed job. Uses the configured backoff strategy
     * to compute delays based on the current attempt number.
     *
     * @return int Delay in seconds to wait before next retry
     */
    public function backoff(): int
    {
        return $this->backoffStrategy->calculate($this->attempts());
    }

    /**
     * Execute the webhook delivery job.
     *
     * Attempts to dispatch the webhook HTTP request. On success, fires
     * a success event and completes. On failure, delegates to failure
     * handling which manages retries and events.
     *
     * @throws MaxRetriesExceededException When all retries are exhausted and throwExceptionOnFailure is true
     * @throws WebhookCallException        When webhook dispatch fails and will be retried
     */
    public function handle(): void
    {
        try {
            $this->dispatchWebhook();
        } catch (Throwable $throwable) {
            $this->handleFailure($throwable);
        }
    }

    /**
     * Handle permanent job failure after all retries are exhausted.
     *
     * Called by Laravel's queue worker when the job is permanently failed.
     * The FinalWebhookCallFailedEvent has already been dispatched in
     * handleFailure, so this serves as the final cleanup hook.
     *
     * @param Throwable $exception The exception that caused the final failure
     */
    public function failed(Throwable $exception): void
    {
        // Final failure event already dispatched in handleFailure
        // This is called by Laravel's queue worker
    }

    /**
     * Dispatch the webhook HTTP request with signature and headers.
     *
     * Generates the cryptographic signature, constructs HTTP headers,
     * fires the dispatching event, and executes the HTTP request.
     * Validates the response status code and throws on non-2xx responses.
     *
     * @throws WebhookCallException When the HTTP request fails or returns a non-2xx status code
     */
    private function dispatchWebhook(): void
    {
        // Encode payload and generate cryptographic signature
        $payloadJson = json_encode($this->payload, JSON_THROW_ON_ERROR);
        $signature = $this->signer->sign($this->webhookId, $this->timestamp, $payloadJson);

        // Merge custom headers with standard webhook headers
        $headers = array_merge($this->headers, [
            'Content-Type' => 'application/json',
            'webhook-id' => $this->webhookId,
            'webhook-timestamp' => (string) $this->timestamp,
            'webhook-signature' => $signature,
        ]);

        // Fire event before dispatch for logging and observability
        event(
            new DispatchingWebhookCallEvent($this->webhookId, $this->url, $this->payload, $headers),
        );

        // Configure HTTP client with timeout and SSL settings
        $client = new Client([
            'timeout' => $this->timeoutInSeconds,
            'verify' => $this->verifySsl,
        ]);

        try {
            $response = $client->request($this->httpVerb, $this->url, [
                'headers' => $headers,
                'body' => $payloadJson,
            ]);

            $statusCode = $response->getStatusCode();

            // Only 2xx responses indicate successful webhook delivery
            if ($statusCode >= 200 && $statusCode < 300) {
                event(
                    new WebhookCallSucceededEvent($this->webhookId, $this->url, $statusCode, $this->attempts()),
                );

                return;
            }

            // 3xx, 4xx, 5xx responses are treated as failures
            throw WebhookCallException::httpError(
                $this->url,
                $statusCode,
                $response->getBody()->getContents(),
            );
        } catch (RequestException $requestException) {
            // Network errors, timeouts, DNS failures, etc.
            throw WebhookCallException::dispatchFailed($this->url, $requestException);
        }
    }

    /**
     * Handle webhook delivery failure and manage retry logic.
     *
     * Fires the failure event for this attempt. If more retries remain,
     * re-throws the exception to trigger Laravel's automatic retry.
     * If all retries are exhausted, fires the final failure event and
     * optionally throws MaxRetriesExceededException based on configuration.
     *
     * @param  Throwable                      $exception The exception that caused this delivery attempt to fail
     * @throws MaxRetriesExceededException    When all retries are exhausted and throwExceptionOnFailure is enabled
     * @throws Throwable|WebhookCallException Re-thrown to trigger Laravel retry if more attempts remain
     */
    private function handleFailure(Throwable $exception): void
    {
        // Fire event for this specific failed attempt
        event(
            new WebhookCallFailedEvent($this->webhookId, $this->url, $this->attempts(), $exception),
        );

        // If we have more attempts remaining, re-throw to trigger Laravel's automatic retry
        // The backoff() method will determine the delay before the next attempt
        throw_if($this->attempts() < $this->tries, $exception);

        // All retries exhausted - fire final failure event for monitoring
        event(
            new FinalWebhookCallFailedEvent($this->webhookId, $this->url, $this->attempts(), $exception),
        );

        // Optionally throw exception based on configuration
        // If false, the failure is silently logged through events only
        if ($this->throwExceptionOnFailure) {
            throw MaxRetriesExceededException::make($this->tries, $this->url);
        }
    }
}
