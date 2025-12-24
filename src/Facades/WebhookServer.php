<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Facades;

use Cline\Webhook\Enums\SignatureVersion;
use Cline\Webhook\Server\Contracts\BackoffStrategy;
use Cline\Webhook\Server\Contracts\Signer;
use Cline\Webhook\Server\WebhookCall;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for sending signed webhook requests to external endpoints.
 *
 * Provides a fluent interface for constructing and dispatching outbound webhook
 * calls with cryptographic signatures, retry logic, and queue integration. Used
 * when your application needs to notify external services of events by sending
 * HTTP requests with signature verification support.
 *
 * ```php
 * WebhookServer::create()
 *     ->url('https://example.com/webhooks')
 *     ->payload(['event' => 'user.created', 'user_id' => 123])
 *     ->useSecret(config('webhooks.secret'))
 *     ->dispatch();
 * ```
 *
 * @method static void        dispatch()                                                                            Send webhook asynchronously via queue
 * @method static void        dispatchIf(bool $condition)                                                           Conditionally send webhook based on boolean
 * @method static void        dispatchSync()                                                                        Send webhook immediately without queueing
 * @method static void        dispatchUnless(bool $condition)                                                       Send webhook unless condition is true
 * @method static WebhookCall doNotVerifySsl()                                                                      Disable SSL certificate verification for endpoint
 * @method static WebhookCall maximumTries(int $tries)                                                              Set maximum retry attempts on failure
 * @method static WebhookCall meta(array<string, mixed> $meta)                                                      Attach custom metadata to webhook record
 * @method static WebhookCall useHttpVerb(string $verb)                           Set HTTP method (POST, PUT, etc.)
 * @method static WebhookCall onQueue(string $queue)                                                                Specify queue name for async dispatch
 * @method static WebhookCall payload(array<string, mixed> $payload)                                                Set webhook request body data
 * @method static WebhookCall signatureVersion(SignatureVersion $version)                                           Choose HMAC or Ed25519 signature algorithm
 * @method static WebhookCall tags(array<int, string>|string $tags)                                                 Add monitoring/filtering tags
 * @method static WebhookCall throwExceptionOnFailure()                                                             Fail job on HTTP error instead of retry
 * @method static WebhookCall timeoutInSeconds(int $seconds)                                                        Set HTTP request timeout duration
 * @method static WebhookCall url(string $url)                                                                      Set destination endpoint URL
 * @method static WebhookCall useBackoffStrategy(BackoffStrategy $strategy)                                         Configure retry delay algorithm
 * @method static WebhookCall useEd25519Key(string $privateKey)                                                     Provide Ed25519 private key for signing
 * @method static WebhookCall useSecret(string $secret)                                                             Provide HMAC secret for signing
 * @method static WebhookCall useSigner(Signer $signer)                                                             Use custom signature implementation
 * @method static WebhookCall webhookId(string $id)                                                                 Set custom webhook identifier
 * @method static WebhookCall withHeaders(array<string, string> $headers)                                           Add custom HTTP headers
 *
 * @author Brian Faust <brian@cline.sh>
 * @see WebhookCall
 */
final class WebhookServer extends Facade
{
    /**
     * Create a new webhook call instance with fluent builder interface.
     *
     * Factory method providing an alternative to direct facade method calls.
     * Returns a fresh WebhookCall builder instance that can be configured
     * with chained method calls before dispatching.
     *
     * @return WebhookCall A new webhook builder instance ready for configuration
     */
    public static function create(): WebhookCall
    {
        return WebhookCall::create();
    }

    /**
     * Get the registered name of the component.
     *
     * Returns the service container binding key that this facade provides
     * access to. Laravel resolves this accessor to retrieve the underlying
     * WebhookCall instance from the container for each facade method call.
     *
     * @return string The fully qualified class name used for container resolution
     */
    protected static function getFacadeAccessor(): string
    {
        return WebhookCall::class;
    }
}
