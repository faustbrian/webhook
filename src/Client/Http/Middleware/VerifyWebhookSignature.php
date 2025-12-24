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
     * Get signature validator instance.
     */
    private function getSignatureValidator(string $configName): SignatureValidator
    {
        /** @var class-string<SignatureValidator> $validatorClass */
        $validatorClass = Config::get(sprintf('webhook.client.configs.%s.signature_validator', $configName));

        return resolve($validatorClass);
    }
}
