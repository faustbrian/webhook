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
use Cline\Webhook\Client\Models\WebhookCall;
use Cline\Webhook\Enums\WebhookStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Cline\Webhook\Client\Jobs\ProcessWebhookJob;
use function array_map;
use function dispatch;
use function in_array;
use function json_decode;
use function mb_strtolower;

/**
 * Processes incoming webhook requests.
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class WebhookProcessor
{
    /**
     * @param string $configName Configuration name from webhook.client.configs
     */
    public function __construct(
        private string $configName = 'default',
    ) {}

    /**
     * Process incoming webhook request.
     */
    public function process(Request $request): Response
    {
        // Verify signature
        if (!$this->verifySignature($request)) {
            event(new InvalidWebhookSignatureEvent($request, $this->configName));

            return new Response('Invalid signature', \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
        }

        // Check if webhook should be processed via profile
        if (!$this->shouldProcess($request)) {
            return new Response('Webhook ignored', \Symfony\Component\HttpFoundation\Response::HTTP_OK);
        }

        // Store webhook
        $webhookCall = $this->storeWebhook($request);

        // Dispatch event
        event(new WebhookReceivedEvent($webhookCall, $this->configName));

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
     * Filter headers based on configuration.
     *
     * @param  array<string, list<string|null>> $allHeaders
     * @param  array<string>                    $storeHeaders
     * @return array<string, string>
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
     * Queue webhook for processing.
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
        /** @var class-string<SignatureValidator> $validatorClass */
        $validatorClass = Config::get(sprintf('webhook.client.configs.%s.signature_validator', $this->configName));

        return resolve($validatorClass);
    }

    /**
     * Get signing secret from config.
     */
    private function getSigningSecret(): string
    {
        /** @var string $secret */
        $secret = Config::get(sprintf('webhook.client.configs.%s.signing_secret', $this->configName));

        return $secret;
    }

    /**
     * Get webhook profile instance.
     */
    private function getWebhookProfile(): WebhookProfile
    {
        /** @var class-string<WebhookProfile> $profileClass */
        $profileClass = Config::get(sprintf('webhook.client.configs.%s.webhook_profile', $this->configName));

        return resolve($profileClass);
    }

    /**
     * Get webhook response instance.
     */
    private function getWebhookResponse(): WebhookResponse
    {
        /** @var class-string<WebhookResponse> $responseClass */
        $responseClass = Config::get(sprintf('webhook.client.configs.%s.webhook_response', $this->configName));

        return resolve($responseClass);
    }

    /**
     * Get webhook model class.
     *
     * @return class-string<WebhookCall>
     */
    private function getWebhookModel(): string
    {
        /** @var class-string<WebhookCall> $modelClass */
        $modelClass = Config::get(sprintf('webhook.client.configs.%s.webhook_model', $this->configName));

        return $modelClass;
    }
}
