<?php

declare(strict_types=1);

namespace Cline\Webhook\Client;

use Cline\Webhook\Client\Contracts\ProcessesWebhook;
use Cline\Webhook\Client\Contracts\SignatureValidator;
use Cline\Webhook\Client\Contracts\WebhookProfile;
use Cline\Webhook\Client\Contracts\WebhookResponse;
use Cline\Webhook\Client\Events\InvalidWebhookSignatureEvent;
use Cline\Webhook\Client\Events\WebhookReceivedEvent;
use Cline\Webhook\Client\Models\WebhookCall;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

/**
 * Processes incoming webhook requests.
 */
final class WebhookProcessor
{
    /**
     * @param  string  $configName  Configuration name from webhook.client.configs
     */
    public function __construct(
        private readonly string $configName = 'default',
    ) {}

    /**
     * Process incoming webhook request.
     */
    public function process(Request $request): Response
    {
        // Verify signature
        if (! $this->verifySignature($request)) {
            InvalidWebhookSignatureEvent::dispatch($request, $this->configName);

            return new Response('Invalid signature', 401);
        }

        // Check if webhook should be processed via profile
        if (! $this->shouldProcess($request)) {
            return new Response('Webhook ignored', 200);
        }

        // Store webhook
        $webhookCall = $this->storeWebhook($request);

        // Dispatch event
        WebhookReceivedEvent::dispatch($webhookCall, $this->configName);

        // Queue processing job
        $this->queueProcessing($webhookCall);

        // Return response
        return $this->getResponse($webhookCall);
    }

    /**
     * Verify webhook signature.
     */
    private function verifySignature(Request $request): bool
    {
        $validator = $this->getSignatureValidator();
        $secret = $this->getSigningSecret();

        return $validator->isValid($request, $secret);
    }

    /**
     * Check if webhook should be processed.
     */
    private function shouldProcess(Request $request): bool
    {
        $profile = $this->getWebhookProfile();

        return $profile->shouldProcess($request);
    }

    /**
     * Store webhook in database.
     */
    private function storeWebhook(Request $request): WebhookCall
    {
        $modelClass = $this->getWebhookModel();
        $storeHeaders = Config::get("webhook.client.configs.{$this->configName}.store_headers", ['*']);

        $headers = $this->filterHeaders($request->headers->all(), $storeHeaders);

        return $modelClass::create([
            'config_name' => $this->configName,
            'webhook_id' => $request->header('webhook-id'),
            'timestamp' => (int) $request->header('webhook-timestamp'),
            'payload' => \json_decode($request->getContent(), true),
            'headers' => $headers,
            'status' => \Cline\Webhook\Enums\WebhookStatus::PENDING,
            'attempts' => 0,
        ]);
    }

    /**
     * Filter headers based on configuration.
     *
     * @param  array<string, array<string>>  $allHeaders
     * @param  array<string>  $storeHeaders
     * @return array<string, string>
     */
    private function filterHeaders(array $allHeaders, array $storeHeaders): array
    {
        // Store all headers if wildcard
        if (\in_array('*', $storeHeaders, true)) {
            return \array_map(fn ($values) => $values[0] ?? '', $allHeaders);
        }

        // Store only specified headers
        $filtered = [];
        foreach ($storeHeaders as $header) {
            $key = \strtolower($header);
            if (isset($allHeaders[$key])) {
                $filtered[$key] = $allHeaders[$key][0] ?? '';
            }
        }

        return $filtered;
    }

    /**
     * Queue webhook for processing.
     */
    private function queueProcessing(WebhookCall $webhookCall): void
    {
        $jobClass = Config::get(
            "webhook.client.configs.{$this->configName}.process_webhook_job",
            \Cline\Webhook\Client\Jobs\ProcessWebhookJob::class
        );

        \dispatch(new $jobClass($webhookCall));
    }

    /**
     * Get webhook response.
     */
    private function getResponse(WebhookCall $webhookCall): Response
    {
        $responseHandler = $this->getWebhookResponse();

        return $responseHandler->response($webhookCall);
    }

    /**
     * Get signature validator instance.
     */
    private function getSignatureValidator(): SignatureValidator
    {
        $validatorClass = Config::get("webhook.client.configs.{$this->configName}.signature_validator");

        return app($validatorClass);
    }

    /**
     * Get signing secret from config.
     */
    private function getSigningSecret(): string
    {
        return Config::get("webhook.client.configs.{$this->configName}.signing_secret");
    }

    /**
     * Get webhook profile instance.
     */
    private function getWebhookProfile(): WebhookProfile
    {
        $profileClass = Config::get("webhook.client.configs.{$this->configName}.webhook_profile");

        return app($profileClass);
    }

    /**
     * Get webhook response instance.
     */
    private function getWebhookResponse(): WebhookResponse
    {
        $responseClass = Config::get("webhook.client.configs.{$this->configName}.webhook_response");

        return app($responseClass);
    }

    /**
     * Get webhook model class.
     *
     * @return class-string<WebhookCall>
     */
    private function getWebhookModel(): string
    {
        return Config::get("webhook.client.configs.{$this->configName}.webhook_model");
    }
}
