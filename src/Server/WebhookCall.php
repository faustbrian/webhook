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
 * @example
 * WebhookCall::create()
 *     ->url('https://example.com/webhook')
 *     ->payload(['event' => 'user.created', 'data' => [...]])
 *     ->dispatch();
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookCall
{
    private string $url = '';

    /** @var array<string, mixed> */
    private array $payload = [];

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    /** @var array<string> */
    private array $tags = [];

    private string $httpVerb = 'POST';

    private int $timeoutInSeconds = 3;

    private int $tries = 3;

    private ?BackoffStrategy $backoffStrategy = null;

    private bool $verifySsl = true;

    private ?Signer $signer = null;

    private ?string $webhookId = null;

    private ?int $timestamp = null;

    private ?string $queue = null;

    private bool $async = true;

    private bool $throwExceptionOnFailure = false;

    private ?string $secret = null;

    private ?string $ed25519PrivateKey = null;

    private ?SignatureVersion $signatureVersion = null;

    /**
     * Create a new webhook call instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the target URL.
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
     * Set the payload.
     *
     * @param array<string, mixed> $payload
     */
    public function payload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Add custom headers.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Add metadata.
     *
     * @param array<string, mixed> $meta
     */
    public function meta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Add tags for Laravel Horizon.
     *
     * @param array<string>|string $tags
     */
    public function tags(array|string $tags): self
    {
        $this->tags = array_merge($this->tags, is_array($tags) ? $tags : [$tags]);

        return $this;
    }

    /**
     * Set HTTP verb.
     */
    public function useHttpVerb(string $verb): self
    {
        $this->httpVerb = mb_strtoupper($verb);

        return $this;
    }

    /**
     * Set timeout in seconds.
     */
    public function timeoutInSeconds(int $seconds): self
    {
        $this->timeoutInSeconds = $seconds;

        return $this;
    }

    /**
     * Set maximum retry attempts.
     */
    public function maximumTries(int $tries): self
    {
        $this->tries = $tries;

        return $this;
    }

    /**
     * Set backoff strategy.
     */
    public function useBackoffStrategy(BackoffStrategy $strategy): self
    {
        $this->backoffStrategy = $strategy;

        return $this;
    }

    /**
     * Disable SSL verification.
     */
    public function doNotVerifySsl(): self
    {
        $this->verifySsl = false;

        return $this;
    }

    /**
     * Set custom signer.
     */
    public function useSigner(Signer $signer): self
    {
        $this->signer = $signer;

        return $this;
    }

    /**
     * Set webhook ID (defaults to generated ULID).
     */
    public function webhookId(string $id): self
    {
        $this->webhookId = $id;

        return $this;
    }

    /**
     * Set custom signing secret for HMAC.
     */
    public function useSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * Set Ed25519 private key.
     */
    public function useEd25519Key(string $privateKey): self
    {
        $this->ed25519PrivateKey = $privateKey;

        return $this;
    }

    /**
     * Set signature version.
     */
    public function signatureVersion(SignatureVersion $version): self
    {
        $this->signatureVersion = $version;

        return $this;
    }

    /**
     * Dispatch asynchronously via queue.
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        $this->async = true;

        return $this;
    }

    /**
     * Dispatch synchronously (blocking).
     */
    public function dispatchSync(): void
    {
        $this->async = false;
        $this->dispatch();
    }

    /**
     * Throw exception on failure instead of silent failure.
     */
    public function throwExceptionOnFailure(): self
    {
        $this->throwExceptionOnFailure = true;

        return $this;
    }

    /**
     * Dispatch the webhook call.
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
     * Conditionally dispatch the webhook.
     */
    public function dispatchIf(bool $condition): void
    {
        if (!$condition) {
            return;
        }

        $this->dispatch();
    }

    /**
     * Conditionally dispatch the webhook (inverse).
     */
    public function dispatchUnless(bool $condition): void
    {
        $this->dispatchIf(!$condition);
    }

    /**
     * Prepare default values from configuration.
     */
    private function prepareDefaults(): void
    {
        $this->httpVerb = $this->httpVerb ?: Config::get('webhook.server.http_verb', 'POST');
        $this->timeoutInSeconds = $this->timeoutInSeconds ?: Config::get('webhook.server.timeout_in_seconds', 3);
        $this->tries = $this->tries ?: Config::get('webhook.server.tries', 3);
        $this->verifySsl = Config::get('webhook.server.verify_ssl', true);
        $this->throwExceptionOnFailure = $this->throwExceptionOnFailure ?: Config::get('webhook.server.throw_exception_on_failure', false);
        $this->queue = $this->queue ?: Config::get('webhook.server.queue');
    }

    /**
     * Resolve the signer instance.
     */
    private function resolveSigner(): Signer
    {
        if ($this->signer) {
            return $this->signer;
        }

        $version = $this->signatureVersion ?? SignatureVersion::from(
            Config::get('webhook.server.signature_version', SignatureVersion::V1_HMAC->value),
        );

        return match ($version) {
            SignatureVersion::V1_HMAC => new HmacSigner(
                $this->secret ?? Config::get('webhook.server.signing_secret'),
            ),
            SignatureVersion::V1A_ED25519 => new Ed25519Signer(
                $this->ed25519PrivateKey ?? Config::get('webhook.server.ed25519_private_key'),
            ),
        };
    }
}
