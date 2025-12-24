<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client;

use Cline\Webhook\Client\Contracts\ProcessesWebhook;
use Cline\Webhook\Client\Contracts\SignatureValidator;
use Cline\Webhook\Client\Contracts\WebhookProfile;
use Cline\Webhook\Client\Contracts\WebhookResponse;
use Cline\Webhook\Client\Events\InvalidWebhookSignatureEvent;
use Cline\Webhook\Client\Events\WebhookReceivedEvent;
use Cline\Webhook\Client\Jobs\ProcessWebhookJob;
use Cline\Webhook\Client\Models\WebhookCall;
use Cline\Webhook\Enums\WebhookStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

use function array_map;
use function dispatch;
use function event;
use function in_array;
use function json_decode;
use function mb_strtolower;
use function resolve;
use function sprintf;

/**
 * Central processor for handling incoming webhook requests end-to-end.
 *
 * Orchestrates the complete webhook handling lifecycle: signature verification,
 * profile-based filtering, payload storage, event dispatching, queue job creation,
 * and HTTP response generation. Coordinates multiple subsystems to provide a
 * complete webhook ingestion pipeline from HTTP request to queued processing.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class WebhookProcessor
{
    /**
     * Create a new webhook processor for a specific configuration.
     *
     * @param string $configName Configuration name from webhook.client.configs array.
     *                           Determines which signature validator, profile, processor,
     *                           and other settings to use. Defaults to 'default'.
     */
    public function __construct(
        private string $configName = 'default',
    ) {}

    /**
     * Process incoming webhook request through the complete handling pipeline.
     *
     * Executes the full webhook lifecycle:
     * 1. Verifies cryptographic signature (returns 401 if invalid)
     * 2. Checks webhook profile to determine if it should be processed (returns 200 if ignored)
     * 3. Stores webhook call in database with PENDING status
     * 4. Dispatches WebhookReceivedEvent for synchronous listeners
     * 5. Queues ProcessWebhookJob for asynchronous processing
     * 6. Returns configured HTTP response to webhook sender
     *
     * @param  Request  $request HTTP request containing webhook headers and JSON payload
     * @return Response HTTP response to return to webhook sender. 401 for invalid
     *                  signatures, 200 for ignored webhooks, or configured response
     *                  handler output for accepted webhooks.
     */
    public function process(Request $request): Response
    {
        // Verify signature
        if (!$this->verifySignature($request)) {
            event(
                new InvalidWebhookSignatureEvent($request, $this->configName),
            );

            return new Response('Invalid signature', \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
        }

        // Check if webhook should be processed via profile
        if (!$this->shouldProcess($request)) {
            return new Response('Webhook ignored', \Symfony\Component\HttpFoundation\Response::HTTP_OK);
        }

        // Store webhook
        $webhookCall = $this->storeWebhook($request);

        // Dispatch event
        event(
            new WebhookReceivedEvent($webhookCall, $this->configName),
        );

        // Queue processing job
        $this->queueProcessing($webhookCall);

        // Return response
        return $this->getResponse($webhookCall);
    }

    /**
     * Verify webhook signature using configured validator.
     *
     * @param  Request $request HTTP request to validate
     * @return bool    True if signature is valid, false otherwise
     */
    private function verifySignature(Request $request): bool
    {
        $validator = $this->getSignatureValidator();
        $secret = $this->getSigningSecret();

        return $validator->isValid($request, $secret);
    }

    /**
     * Check if webhook should be processed using configured profile.
     *
     * @param  Request $request HTTP request to evaluate
     * @return bool    True if webhook should be processed, false to ignore
     */
    private function shouldProcess(Request $request): bool
    {
        $profile = $this->getWebhookProfile();

        return $profile->shouldProcess($request);
    }

    /**
     * Store webhook call in database with PENDING status.
     *
     * Extracts headers, payload, and metadata from request and creates a new
     * WebhookCall record. Filters stored headers based on configuration to
     * avoid storing sensitive data unnecessarily.
     *
     * @param  Request     $request HTTP request containing webhook data
     * @return WebhookCall The newly created webhook call record
     */
    private function storeWebhook(Request $request): WebhookCall
    {
        $modelClass = $this->getWebhookModel();

        /** @var array<string> $storeHeaders */
        $storeHeaders = Config::get(sprintf('webhook.client.configs.%s.store_headers', $this->configName), ['*']);

        $headers = $this->filterHeaders($request->headers->all(), $storeHeaders);

        return $modelClass::query()->create([
            'config_name' => $this->configName,
            'webhook_id' => $request->header('webhook-id'),
            'timestamp' => (int) $request->header('webhook-timestamp'),
            'payload' => json_decode($request->getContent(), true),
            'headers' => $headers,
            'status' => WebhookStatus::PENDING,
            'attempts' => 0,
        ]);
    }

    /**
     * Filter HTTP headers based on configuration whitelist.
     *
     * Filters incoming request headers to only store those specified in the
     * store_headers configuration. Supports wildcard (*) to store all headers
     * or specific header names for selective storage. Converts multi-value
     * headers to single string by taking first value.
     *
     * @param  array<string, list<null|string>> $allHeaders   All HTTP headers from request
     * @param  array<string>                    $storeHeaders Configured header names to store, or ['*'] for all
     * @return array<string, string>            Filtered headers as name => value pairs
     */
    private function filterHeaders(array $allHeaders, array $storeHeaders): array
    {
        // Store all headers if wildcard
        if (in_array('*', $storeHeaders, true)) {
            return array_map(fn (array $values): string => $values[0] ?? '', $allHeaders);
        }

        // Store only specified headers
        $filtered = [];

        foreach ($storeHeaders as $header) {
            $key = mb_strtolower($header);

            if (!isset($allHeaders[$key])) {
                continue;
            }

            $filtered[$key] = $allHeaders[$key][0] ?? '';
        }

        return $filtered;
    }

    /**
     * Queue webhook for asynchronous processing.
     *
     * Dispatches the configured job class to Laravel's queue system for
     * background processing. Uses ProcessWebhookJob by default, but allows
     * custom job classes via configuration.
     *
     * @param WebhookCall $webhookCall The webhook call to process
     */
    private function queueProcessing(WebhookCall $webhookCall): void
    {
        /** @var class-string<ProcessesWebhook> $jobClass */
        $jobClass = Config::get(
            sprintf('webhook.client.configs.%s.process_webhook_job', $this->configName),
            ProcessWebhookJob::class,
        );

        dispatch(
            new $jobClass($webhookCall),
        );
    }

    /**
     * Get HTTP response to return to webhook sender.
     *
     * @param  WebhookCall $webhookCall The stored webhook call
     * @return Response    HTTP response from configured response handler
     */
    private function getResponse(WebhookCall $webhookCall): Response
    {
        $responseHandler = $this->getWebhookResponse();

        return $responseHandler->response($webhookCall);
    }

    /**
     * Get signature validator instance from configuration.
     *
     * @return SignatureValidator Resolved signature validator (HMAC or Ed25519)
     */
    private function getSignatureValidator(): SignatureValidator
    {
        /** @var class-string<SignatureValidator> $validatorClass */
        $validatorClass = Config::get(sprintf('webhook.client.configs.%s.signature_validator', $this->configName));

        return resolve($validatorClass);
    }

    /**
     * Get signing secret from configuration.
     *
     * @return string Shared secret or public key for signature verification
     */
    private function getSigningSecret(): string
    {
        /** @var string */
        return Config::get(sprintf('webhook.client.configs.%s.signing_secret', $this->configName));
    }

    /**
     * Get webhook profile instance from configuration.
     *
     * @return WebhookProfile Resolved profile for filtering webhooks
     */
    private function getWebhookProfile(): WebhookProfile
    {
        /** @var class-string<WebhookProfile> $profileClass */
        $profileClass = Config::get(sprintf('webhook.client.configs.%s.webhook_profile', $this->configName));

        return resolve($profileClass);
    }

    /**
     * Get webhook response handler instance from configuration.
     *
     * @return WebhookResponse Resolved response handler for HTTP responses
     */
    private function getWebhookResponse(): WebhookResponse
    {
        /** @var class-string<WebhookResponse> $responseClass */
        $responseClass = Config::get(sprintf('webhook.client.configs.%s.webhook_response', $this->configName));

        return resolve($responseClass);
    }

    /**
     * Get webhook model class from configuration.
     *
     * @return class-string<WebhookCall> Configured WebhookCall model class
     */
    private function getWebhookModel(): string
    {
        /** @var class-string<WebhookCall> */
        return Config::get(sprintf('webhook.client.configs.%s.webhook_model', $this->configName));
    }
}
