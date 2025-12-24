<?php

declare(strict_types=1);

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

/**
 * Job to dispatch webhook calls with retry logic and exponential backoff.
 */
final class CallWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $webhookId  Unique webhook identifier
     * @param  string  $url  Target URL
     * @param  string  $httpVerb  HTTP method
     * @param  array<string, mixed>  $payload  Webhook payload
     * @param  array<string, string>  $headers  Custom headers
     * @param  array<string, mixed>  $meta  Metadata
     * @param  array<string>  $tags  Queue tags
     * @param  Signer  $signer  Signature generator
     * @param  int  $timestamp  Unix timestamp
     * @param  int  $tries  Maximum attempts
     * @param  BackoffStrategy  $backoffStrategy  Retry backoff calculator
     * @param  int  $timeoutInSeconds  Request timeout
     * @param  bool  $verifySsl  Verify SSL certificates
     * @param  bool  $throwExceptionOnFailure  Throw on final failure
     */
    public function __construct(
        private readonly string $webhookId,
        private readonly string $url,
        private readonly string $httpVerb,
        private readonly array $payload,
        private readonly array $headers,
        private readonly array $meta,
        private readonly array $tags,
        private readonly Signer $signer,
        private readonly int $timestamp,
        public int $tries,
        private readonly BackoffStrategy $backoffStrategy,
        private readonly int $timeoutInSeconds,
        private readonly bool $verifySsl,
        private readonly bool $throwExceptionOnFailure,
    ) {}

    /**
     * Get the tags for Laravel Horizon.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return \array_merge($this->tags, ['webhook', "webhook:{$this->webhookId}"]);
    }

    /**
     * Calculate backoff delay for retry.
     */
    public function backoff(): int
    {
        return $this->backoffStrategy->calculate($this->attempts());
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->dispatchWebhook();
        } catch (\Throwable $exception) {
            $this->handleFailure($exception);
        }
    }

    /**
     * Dispatch the webhook HTTP request.
     *
     * @throws WebhookCallException
     */
    private function dispatchWebhook(): void
    {
        $payloadJson = \json_encode($this->payload, \JSON_THROW_ON_ERROR);
        $signature = $this->signer->sign($this->webhookId, $this->timestamp, $payloadJson);

        $headers = \array_merge($this->headers, [
            'Content-Type' => 'application/json',
            'webhook-id' => $this->webhookId,
            'webhook-timestamp' => (string) $this->timestamp,
            'webhook-signature' => $signature,
        ]);

        DispatchingWebhookCallEvent::dispatch(
            $this->webhookId,
            $this->url,
            $this->payload,
            $headers
        );

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

            // Consider 2xx responses as success
            if ($statusCode >= 200 && $statusCode < 300) {
                WebhookCallSucceededEvent::dispatch(
                    $this->webhookId,
                    $this->url,
                    $statusCode,
                    $this->attempts()
                );

                return;
            }

            // Non-2xx response
            throw WebhookCallException::httpError(
                $this->url,
                $statusCode,
                $response->getBody()->getContents()
            );
        } catch (RequestException $exception) {
            throw WebhookCallException::dispatchFailed($this->url, $exception);
        }
    }

    /**
     * Handle webhook failure.
     *
     * @throws WebhookCallException|MaxRetriesExceededException
     */
    private function handleFailure(\Throwable $exception): void
    {
        WebhookCallFailedEvent::dispatch(
            $this->webhookId,
            $this->url,
            $this->attempts(),
            $exception
        );

        // Check if we have more attempts
        if ($this->attempts() < $this->tries) {
            // Re-throw to trigger Laravel's retry mechanism
            throw $exception;
        }

        // Final failure - all retries exhausted
        FinalWebhookCallFailedEvent::dispatch(
            $this->webhookId,
            $this->url,
            $this->attempts(),
            $exception
        );

        if ($this->throwExceptionOnFailure) {
            throw MaxRetriesExceededException::make($this->tries, $this->url);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Final failure event already dispatched in handleFailure
        // This is called by Laravel's queue worker
    }
}
