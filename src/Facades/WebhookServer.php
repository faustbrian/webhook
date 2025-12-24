<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Facades;

use Cline\Webhook\Server\WebhookCall;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for server-side webhook dispatch.
 *
 * @method static void dispatch()
 * @method static void dispatchIf(bool $condition)
 * @method static void dispatchSync()
 * @method static void dispatchUnless(bool $condition)
 * @method static WebhookCall doNotVerifySsl()
 * @method static WebhookCall maximumTries(int $tries)
 * @method static WebhookCall meta(array $meta)
 * @method static WebhookCall onQueue(string $queue)
 * @method static WebhookCall payload(array $payload)
 * @method static WebhookCall signatureVersion(SignatureVersion $version)
 * @method static WebhookCall tags(array|string $tags)
 * @method static WebhookCall throwExceptionOnFailure()
 * @method static WebhookCall timeoutInSeconds(int $seconds)
 * @method static WebhookCall url(string $url)
 * @method static WebhookCall useBackoffStrategy(BackoffStrategy $strategy)
 * @method static WebhookCall useEd25519Key(string $privateKey)
 * @method static WebhookCall useHttpVerb(string $verb)
 * @method static WebhookCall useSecret(string $secret)
 * @method static WebhookCall useSigner(Signer $signer)
 * @method static WebhookCall webhookId(string $id)
 * @method static WebhookCall withHeaders(array $headers)
 *
 * @author Brian Faust <brian@cline.sh>
 * @see WebhookCall
 */
final class WebhookServer extends Facade
{
    /**
     * Create a new webhook call instance.
     */
    public static function create(): WebhookCall
    {
        return WebhookCall::create();
    }

    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return WebhookCall::class;
    }
}
