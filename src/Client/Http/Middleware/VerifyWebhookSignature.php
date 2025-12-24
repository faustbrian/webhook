<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Http\Middleware;

use Cline\Webhook\Client\Contracts\SignatureValidator;
use Cline\Webhook\Client\Events\InvalidWebhookSignatureEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

/**
 * Middleware to verify webhook signatures.
 */
final class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $configName = 'default'): Response
    {
        $validator = $this->getSignatureValidator($configName);
        $secret = Config::get("webhook.client.configs.{$configName}.signing_secret");

        if (! $validator->isValid($request, $secret)) {
            InvalidWebhookSignatureEvent::dispatch($request, $configName);

            return new Response('Invalid signature', 401);
        }

        return $next($request);
    }

    /**
     * Get signature validator instance.
     */
    private function getSignatureValidator(string $configName): SignatureValidator
    {
        $validatorClass = Config::get("webhook.client.configs.{$configName}.signature_validator");

        return app($validatorClass);
    }
}
