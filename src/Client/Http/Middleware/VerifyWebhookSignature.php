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

use function event;
use function resolve;
use function sprintf;

/**
 * Middleware to verify webhook signature authenticity before processing.
 *
 * Validates incoming webhook requests by checking cryptographic signatures
 * using the configured signature validator. Rejects requests with invalid
 * signatures to prevent unauthorized webhook processing and potential
 * security vulnerabilities from spoofed webhook calls.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class VerifyWebhookSignature
{
    /**
     * Handle an incoming webhook request and verify its signature.
     *
     * Validates the webhook signature using the configured validator and secret
     * for the specified configuration. Dispatches an InvalidWebhookSignatureEvent
     * and returns 401 Unauthorized if validation fails. Otherwise, passes the
     * request through to the next middleware in the pipeline.
     *
     * @param  Request                    $request    HTTP request containing webhook payload and signature headers
     * @param  Closure(Request): Response $next       Next middleware in the pipeline
     * @param  string                     $configName Configuration name from webhook.client.configs array.
     *                                                Determines which signature validator and signing
     *                                                secret to use for verification. Defaults to 'default'.
     * @return Response                   HTTP response, either 401 for invalid signatures or the
     *                                    response from the next middleware for valid signatures
     */
    public function handle(Request $request, Closure $next, string $configName = 'default'): Response
    {
        $validator = $this->getSignatureValidator($configName);

        /** @var string $secret */
        $secret = Config::get(sprintf('webhook.client.configs.%s.signing_secret', $configName));

        if (!$validator->isValid($request, $secret)) {
            event(
                new InvalidWebhookSignatureEvent($request, $configName),
            );

            return new Response('Invalid signature', \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    /**
     * Get signature validator instance for the specified configuration.
     *
     * Resolves the configured signature validator class from the service container.
     * The validator class is determined by the signature_validator setting in the
     * webhook.client.configs array for the given configuration name.
     *
     * @param  string             $configName Configuration name to look up validator settings
     * @return SignatureValidator Resolved signature validator instance
     */
    private function getSignatureValidator(string $configName): SignatureValidator
    {
        /** @var class-string<SignatureValidator> $validatorClass */
        $validatorClass = Config::get(sprintf('webhook.client.configs.%s.signature_validator', $configName));

        return resolve($validatorClass);
    }
}
