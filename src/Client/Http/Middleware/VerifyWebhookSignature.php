<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Http\Middleware;

use Cline\Webhook\Client\Contracts\SignatureValidator;
use Cline\Webhook\Client\Events\InvalidWebhookSignatureEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

use function app;

/**
 * Middleware to verify webhook signatures.
 * @author Brian Faust <brian@cline.sh>
 */
final class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string $configName = 'default'): Response
    {
        $validator = $this->getSignatureValidator($configName);
        $secret = Config::get("webhook.client.configs.{$configName}.signing_secret");

        if (!$validator->isValid($request, $secret)) {
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
