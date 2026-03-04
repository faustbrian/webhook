<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server;

use Cline\Webhook\Enums\SignatureVersion;
use Cline\Webhook\Server\Contracts\BackoffStrategy;
use Cline\Webhook\Server\Contracts\Signer;
use Cline\Webhook\Server\Exceptions\InvalidUrlException;
use Cline\Webhook\Server\Jobs\CallWebhookJob;
use Cline\Webhook\Server\Signers\Ed25519Signer;
use Cline\Webhook\Server\Signers\HmacSigner;
use Cline\Webhook\Server\Strategies\ExponentialBackoffStrategy;
use Cline\Webhook\Support\IdGenerator;
use Cline\Webhook\Support\TimestampValidator;
use Illuminate\Support\Facades\Config;

use const FILTER_VALIDATE_URL;

use function array_merge;
use function dispatch;
use function filter_var;
use function is_array;
use function mb_strtoupper;

/**
 * Fluent API for dispatching webhooks with Standard Webhooks compliance.
 *
 * Provides a builder-style interface for configuring and dispatching webhook
 * HTTP requests with automatic signing, retry logic, and queue integration.
 * Supports both HMAC-SHA256 and Ed25519 signature methods per the Standard
 * Webhooks specification.
 *
 * ```php
 * WebhookCall::create()
 *     ->url('https://example.com/webhook')
 *     ->payload(['event' => 'user.created', 'data' => [...]])
 *     ->useSecret('your-signing-secret')
 *     ->dispatch();
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookCall
{
    /**
     * Target URL where the webhook will be sent.
     */
    private string $url = '';

    /**
     * JSON-serializable payload data to send in the webhook body.
     *
     * @var array<string, mixed>
     */
    private array $payload = [];

    /**
     * Additional HTTP headers to include in the webhook request.
     *
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Metadata attached to the webhook job for tracking and debugging.
     *
     * @var array<string, mixed>
     */
    private array $meta = [];

    /**
     * Laravel Horizon tags for job filtering and monitoring.
     *
     * @var array<string>
     */
    private array $tags = [];

    /**
     * HTTP verb to use for the webhook request.
     */
    private string $httpVerb = 'POST';

    /**
     * Request timeout in seconds before giving up.
     */
    private int $timeoutInSeconds = 3;

    /**
     * Maximum number of retry attempts on failure.
     */
    private int $tries = 3;

    /**
     * Strategy for calculating retry delays between attempts.
     */
    private ?BackoffStrategy $backoffStrategy = null;

    /**
     * Whether to verify SSL certificates on HTTPS requests.
     */
    private bool $verifySsl = true;

    /**
     * Custom signature implementation for webhook signing.
     */
    private ?Signer $signer = null;

    /**
     * Unique identifier for this webhook (defaults to auto-generated ULID).
     */
    private ?string $webhookId = null;

    /**
     * Unix timestamp when the webhook was created (auto-generated if null).
     *
     * @phpstan-ignore-next-line property.onlyRead
     */
    private ?int $timestamp = null;

    /**
     * Laravel queue name for async dispatch (null for default queue).
     */
    private ?string $queue = null;

    /**
     * Whether to dispatch asynchronously via queue or synchronously.
     */
    private bool $async = true;

    /**
     * Whether to throw exceptions on webhook delivery failure.
     */
    private bool $throwExceptionOnFailure = false;

    /**
     * HMAC signing secret (used when signature version is V1_HMAC).
     */
    private ?string $secret = null;

    /**
     * Ed25519 private key (used when signature version is V1A_ED25519).
     */
    private ?string $ed25519PrivateKey = null;

    /**
     * Signature algorithm version to use for signing.
     */
    private ?SignatureVersion $signatureVersion = null;

    /**
     * Create a new webhook call instance.
     *
     * @return self Fresh webhook call builder instance for configuration
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the target URL where the webhook will be sent.
     *
     * @param  string              $url Valid HTTP/HTTPS URL for the webhook endpoint
     * @throws InvalidUrlException When the provided URL is not valid
     * @return self                Fluent interface for method chaining
     */
    public function url(string $url): self
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw InvalidUrlException::make($url);
        }

        $this->url = $url;

        return $this;
    }

    /**
     * Set the webhook payload data.
     *
     * @param  array<string, mixed> $payload JSON-serializable data to send in webhook body
     * @return self                 Fluent interface for method chaining
     */
    public function payload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Add custom HTTP headers to the webhook request.
     *
     * @param  array<string, string> $headers Header name-value pairs to include
     * @return self                  Fluent interface for method chaining
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Add metadata for tracking and debugging.
     *
     * @param  array<string, mixed> $meta Arbitrary metadata attached to the webhook job
     * @return self                 Fluent interface for method chaining
     */
    public function meta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Add Laravel Horizon tags for job filtering and monitoring.
     *
     * @param  array<string>|string $tags Single tag or array of tags for Horizon
     * @return self                 Fluent interface for method chaining
     */
    public function tags(array|string $tags): self
    {
        $this->tags = array_merge($this->tags, is_array($tags) ? $tags : [$tags]);

        return $this;
    }

    /**
     * Set the HTTP verb for the webhook request.
     *
     * @param  string $verb HTTP method (e.g., 'POST', 'PUT', 'PATCH')
     * @return self   Fluent interface for method chaining
     */
    public function useHttpVerb(string $verb): self
    {
        $this->httpVerb = mb_strtoupper($verb);

        return $this;
    }

    /**
     * Set the request timeout duration.
     *
     * @param  int  $seconds Maximum seconds to wait for webhook response
     * @return self Fluent interface for method chaining
     */
    public function timeoutInSeconds(int $seconds): self
    {
        $this->timeoutInSeconds = $seconds;

        return $this;
    }

    /**
     * Set the maximum number of retry attempts.
     *
     * @param  int  $tries Total attempts including initial request (minimum 1)
     * @return self Fluent interface for method chaining
     */
    public function maximumTries(int $tries): self
    {
        $this->tries = $tries;

        return $this;
    }

    /**
     * Set a custom backoff strategy for retry delays.
     *
     * @param  BackoffStrategy $strategy Strategy instance for calculating retry delays
     * @return self            Fluent interface for method chaining
     */
    public function useBackoffStrategy(BackoffStrategy $strategy): self
    {
        $this->backoffStrategy = $strategy;

        return $this;
    }

    /**
     * Disable SSL certificate verification for HTTPS requests.
     *
     * @return self Fluent interface for method chaining
     */
    public function doNotVerifySsl(): self
    {
        $this->verifySsl = false;

        return $this;
    }

    /**
     * Set a custom signature implementation.
     *
     * @param  Signer $signer Custom signer instance for webhook signature generation
     * @return self   Fluent interface for method chaining
     */
    public function useSigner(Signer $signer): self
    {
        $this->signer = $signer;

        return $this;
    }

    /**
     * Set a custom webhook identifier.
     *
     * @param  string $id Custom webhook ID (defaults to auto-generated ULID if not set)
     * @return self   Fluent interface for method chaining
     */
    public function webhookId(string $id): self
    {
        $this->webhookId = $id;

        return $this;
    }

    /**
     * Set the HMAC signing secret.
     *
     * @param  string $secret Shared secret key for HMAC-SHA256 signature generation
     * @return self   Fluent interface for method chaining
     */
    public function useSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * Set the Ed25519 private key for signing.
     *
     * @param  string $privateKey Base64-encoded Ed25519 private key
     * @return self   Fluent interface for method chaining
     */
    public function useEd25519Key(string $privateKey): self
    {
        $this->ed25519PrivateKey = $privateKey;

        return $this;
    }

    /**
     * Set the signature algorithm version.
     *
     * @param  SignatureVersion $version Signature version (V1_HMAC or V1A_ED25519)
     * @return self             Fluent interface for method chaining
     */
    public function signatureVersion(SignatureVersion $version): self
    {
        $this->signatureVersion = $version;

        return $this;
    }

    /**
     * Dispatch the webhook asynchronously on a specific queue.
     *
     * @param  string $queue Laravel queue name for async processing
     * @return self   Fluent interface for method chaining
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        $this->async = true;

        return $this;
    }

    /**
     * Dispatch the webhook synchronously (blocks until completion).
     */
    public function dispatchSync(): void
    {
        $this->async = false;
        $this->dispatch();
    }

    /**
     * Enable exception throwing on webhook delivery failure.
     *
     * @return self Fluent interface for method chaining
     */
    public function throwExceptionOnFailure(): self
    {
        $this->throwExceptionOnFailure = true;

        return $this;
    }

    /**
     * Dispatch the configured webhook call.
     *
     * Applies configuration defaults, creates a webhook job with all settings,
     * and dispatches it either asynchronously via queue or synchronously based
     * on the configured dispatch mode.
     */
    public function dispatch(): void
    {
        $this->prepareDefaults();

        $job = new CallWebhookJob(
            webhookId: $this->webhookId ?? IdGenerator::generate(),
            url: $this->url,
            httpVerb: $this->httpVerb,
            payload: $this->payload,
            headers: $this->headers,
            meta: $this->meta,
            tags: $this->tags,
            signer: $this->resolveSigner(),
            timestamp: $this->timestamp ?? TimestampValidator::generate(),
            tries: $this->tries,
            backoffStrategy: $this->backoffStrategy ?? new ExponentialBackoffStrategy(),
            timeoutInSeconds: $this->timeoutInSeconds,
            verifySsl: $this->verifySsl,
            throwExceptionOnFailure: $this->throwExceptionOnFailure,
        );

        if ($this->async) {
            if ($this->queue) {
                $job->onQueue($this->queue);
            }

            dispatch($job);
        } else {
            $job->handle();
        }
    }

    /**
     * Conditionally dispatch the webhook based on a boolean condition.
     *
     * @param bool $condition When true, webhook is dispatched; when false, no action taken
     */
    public function dispatchIf(bool $condition): void
    {
        if (!$condition) {
            return;
        }

        $this->dispatch();
    }

    /**
     * Conditionally dispatch the webhook unless a condition is true.
     *
     * @param bool $condition When false, webhook is dispatched; when true, no action taken
     */
    public function dispatchUnless(bool $condition): void
    {
        $this->dispatchIf(!$condition);
    }

    /**
     * Apply default values from configuration for unset properties.
     *
     * Loads defaults from the webhook.server configuration section for any
     * properties that weren't explicitly set via fluent methods.
     */
    private function prepareDefaults(): void
    {
        /** @var string $httpVerb */
        $httpVerb = Config::get('webhook.server.http_verb', 'POST');
        $this->httpVerb = $this->httpVerb ?: $httpVerb;

        /** @var int $timeout */
        $timeout = Config::get('webhook.server.timeout_in_seconds', 3);
        $this->timeoutInSeconds = $this->timeoutInSeconds ?: $timeout;

        /** @var int $tries */
        $tries = Config::get('webhook.server.tries', 3);
        $this->tries = $this->tries ?: $tries;

        /** @var bool $verifySsl */
        $verifySsl = Config::get('webhook.server.verify_ssl', true);
        $this->verifySsl = $verifySsl;

        /** @var bool $throwOnFailure */
        $throwOnFailure = Config::get('webhook.server.throw_exception_on_failure', false);
        $this->throwExceptionOnFailure = $this->throwExceptionOnFailure ?: $throwOnFailure;

        /** @var null|string $queue */
        $queue = Config::get('webhook.server.queue');
        $this->queue = $this->queue ?: $queue;
    }

    /**
     * Resolve and instantiate the appropriate signer implementation.
     *
     * Returns the custom signer if set, otherwise creates a signer based on
     * the configured signature version (HMAC or Ed25519) with appropriate
     * keys from configuration or fluent methods.
     *
     * @return Signer Configured signer instance for webhook signature generation
     */
    private function resolveSigner(): Signer
    {
        if ($this->signer instanceof Signer) {
            return $this->signer;
        }

        /** @var int|string $configVersion */
        $configVersion = Config::get('webhook.server.signature_version', SignatureVersion::V1_HMAC->value);
        $version = $this->signatureVersion ?? SignatureVersion::from($configVersion);

        return match ($version) {
            SignatureVersion::V1_HMAC => new HmacSigner(
                $this->secret ?? $this->getConfigString('webhook.server.signing_secret'),
            ),
            SignatureVersion::V1A_ED25519 => new Ed25519Signer(
                $this->ed25519PrivateKey ?? $this->getConfigString('webhook.server.ed25519_private_key'),
            ),
        };
    }

    /**
     * Retrieve a string value from Laravel configuration.
     *
     * @param  string $key Configuration key to retrieve
     * @return string Configuration value cast to string
     */
    private function getConfigString(string $key): string
    {
        /** @var string */
        return Config::get($key);
    }
}
