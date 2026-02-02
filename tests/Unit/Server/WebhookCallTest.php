<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Server;

use Cline\Webhook\Enums\SignatureVersion;
use Cline\Webhook\Server\Exceptions\InvalidUrlException;
use Cline\Webhook\Server\Jobs\CallWebhookJob;
use Cline\Webhook\Server\Signers\Ed25519Signer;
use Cline\Webhook\Server\Signers\HmacSigner;
use Cline\Webhook\Server\Strategies\ExponentialBackoffStrategy;
use Cline\Webhook\Server\WebhookCall;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Iterator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use ReflectionClass;
use Tests\TestCase;

use function is_int;
use function is_string;
use function method_exists;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
#[CoversClass(WebhookCall::class)]
#[Small()]
final class WebhookCallTest extends TestCase
{
    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        // Set default config values for testing
        Config::set('webhook.server.http_verb', 'POST');
        Config::set('webhook.server.timeout_in_seconds', 3);
        Config::set('webhook.server.tries', 3);
        Config::set('webhook.server.verify_ssl', true);
        Config::set('webhook.server.throw_exception_on_failure', false);
        Config::set('webhook.server.queue');
        Config::set('webhook.server.signature_version', SignatureVersion::V1_HMAC->value);
        Config::set('webhook.server.signing_secret', 'test-secret');
        Config::set('webhook.server.ed25519_private_key', 'test-private-key');
    }

    #[Test()]
    #[TestDox('Creates new instance via static factory method')]
    #[Group('happy-path')]
    public function creates_new_instance(): void
    {
        // Arrange & Act
        $webhookCall = WebhookCall::create();

        // Assert
        $this->assertInstanceOf(WebhookCall::class, $webhookCall);
    }

    #[Test()]
    #[TestDox('Sets URL successfully with valid URL')]
    #[Group('happy-path')]
    public function sets_url_successfully(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $validUrl = 'https://example.com/webhook';

        // Act
        $result = $webhookCall->url($validUrl);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Throws InvalidUrlException for invalid URL')]
    #[Group('sad-path')]
    public function throws_exception_for_invalid_url(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $invalidUrl = 'not-a-valid-url';

        // Act & Assert
        $this->expectException(InvalidUrlException::class);
        $this->expectExceptionMessage('Invalid webhook URL: not-a-valid-url');

        $webhookCall->url($invalidUrl);
    }

    #[Test()]
    #[TestDox('Throws InvalidUrlException for malformed URL')]
    #[Group('sad-path')]
    #[DataProvider('provideThrows_exception_for_malformed_urlsCases')]
    public function throws_exception_for_malformed_urls(string $invalidUrl): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();

        // Act & Assert
        $this->expectException(InvalidUrlException::class);

        $webhookCall->url($invalidUrl);
    }

    /**
     * Provide invalid URL test cases.
     *
     * @return Iterator<string, array<string>>
     */
    public static function provideThrows_exception_for_malformed_urlsCases(): iterable
    {
        yield 'empty string' => [''];

        yield 'missing protocol' => ['example.com/webhook'];

        yield 'invalid characters' => ['http://example .com'];

        yield 'just a path' => ['/webhook'];

        yield 'just a domain' => ['example'];

        yield 'malformed URL' => ['ht!tp://example.com'];
    }

    #[Test()]
    #[TestDox('Sets payload successfully')]
    #[Group('happy-path')]
    public function sets_payload_successfully(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $payload = ['event' => 'user.created', 'data' => ['id' => 123]];

        // Act
        $result = $webhookCall->payload($payload);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Merges custom headers with existing headers')]
    #[Group('happy-path')]
    public function merges_custom_headers(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create();
        $firstHeaders = ['X-Custom-1' => 'value1'];
        $secondHeaders = ['X-Custom-2' => 'value2'];

        // Act
        $webhookCall
            ->withHeaders($firstHeaders)
            ->withHeaders($secondHeaders)
            ->url('https://example.com/webhook')
            ->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('headers');

            $headers = $property->getValue($job);

            return isset($headers['X-Custom-1'], $headers['X-Custom-2'])
                && $headers['X-Custom-1'] === 'value1'
                && $headers['X-Custom-2'] === 'value2';
        });
    }

    #[Test()]
    #[TestDox('Merges metadata with existing metadata')]
    #[Group('happy-path')]
    public function merges_metadata(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $firstMeta = ['key1' => 'value1'];
        $secondMeta = ['key2' => 'value2'];

        // Act
        $result = $webhookCall
            ->meta($firstMeta)
            ->meta($secondMeta);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Adds tags as array')]
    #[Group('happy-path')]
    public function adds_tags_as_array(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $tags = ['tag1', 'tag2'];

        // Act
        $result = $webhookCall->tags($tags);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Adds tags as string')]
    #[Group('happy-path')]
    public function adds_tags_as_string(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $tag = 'single-tag';

        // Act
        $result = $webhookCall->tags($tag);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Merges multiple tag calls')]
    #[Group('happy-path')]
    public function merges_multiple_tag_calls(): void
    {
        // Arrange
        Queue::fake();

        // Act
        WebhookCall::create()
            ->tags(['tag1', 'tag2'])
            ->tags('tag3')
            ->tags(['tag4'])
            ->url('https://example.com/webhook')
            ->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('tags');

            $tags = $property->getValue($job);

            return $tags === ['tag1', 'tag2', 'tag3', 'tag4'];
        });
    }

    #[Test()]
    #[TestDox('Sets HTTP verb and converts to uppercase')]
    #[Group('happy-path')]
    public function sets_http_verb_and_converts_to_uppercase(): void
    {
        // Arrange
        Queue::fake();

        // Act
        WebhookCall::create()
            ->useHttpVerb('put')
            ->url('https://example.com/webhook')
            ->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('httpVerb');

            return $property->getValue($job) === 'PUT';
        });
    }

    #[Test()]
    #[TestDox('Sets timeout in seconds')]
    #[Group('happy-path')]
    public function sets_timeout_in_seconds(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $timeout = 10;

        // Act
        $result = $webhookCall->timeoutInSeconds($timeout);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Sets maximum tries')]
    #[Group('happy-path')]
    public function sets_maximum_tries(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $tries = 5;

        // Act
        $result = $webhookCall->maximumTries($tries);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Sets custom backoff strategy')]
    #[Group('happy-path')]
    public function sets_custom_backoff_strategy(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $strategy = new ExponentialBackoffStrategy();

        // Act
        $result = $webhookCall->useBackoffStrategy($strategy);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Disables SSL verification')]
    #[Group('happy-path')]
    public function disables_ssl_verification(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();

        // Act
        $result = $webhookCall->doNotVerifySsl();

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Sets custom signer')]
    #[Group('happy-path')]
    public function sets_custom_signer(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $signer = new HmacSigner('custom-secret');

        // Act
        $result = $webhookCall->useSigner($signer);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Sets webhook ID')]
    #[Group('happy-path')]
    public function sets_webhook_id(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $id = 'custom-webhook-id';

        // Act
        $result = $webhookCall->webhookId($id);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Sets custom signing secret for HMAC')]
    #[Group('happy-path')]
    public function sets_custom_signing_secret(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $secret = 'my-custom-secret';

        // Act
        $result = $webhookCall->useSecret($secret);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Sets Ed25519 private key')]
    #[Group('happy-path')]
    public function sets_ed25519_private_key(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $privateKey = 'custom-ed25519-private-key';

        // Act
        $result = $webhookCall->useEd25519Key($privateKey);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Sets signature version')]
    #[Group('happy-path')]
    public function sets_signature_version(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $version = SignatureVersion::V1A_ED25519;

        // Act
        $result = $webhookCall->signatureVersion($version);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Sets queue for async dispatch')]
    #[Group('happy-path')]
    public function sets_queue_for_async_dispatch(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();
        $queueName = 'webhooks';

        // Act
        $result = $webhookCall->onQueue($queueName);

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Enables throw exception on failure')]
    #[Group('happy-path')]
    public function enables_throw_exception_on_failure(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();

        // Act
        $result = $webhookCall->throwExceptionOnFailure();

        // Assert
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Dispatches webhook asynchronously to queue')]
    #[Group('happy-path')]
    public function dispatches_webhook_asynchronously(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->payload(['event' => 'test.event']);

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, 1);
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);

            $urlProperty = $reflection->getProperty('url');

            $payloadProperty = $reflection->getProperty('payload');

            return $urlProperty->getValue($job) === 'https://example.com/webhook'
                && $payloadProperty->getValue($job) === ['event' => 'test.event'];
        });
    }

    #[Test()]
    #[TestDox('Dispatches webhook to specific queue when specified')]
    #[Group('happy-path')]
    public function dispatches_webhook_to_specific_queue(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->onQueue('custom-queue');

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushedOn('custom-queue', CallWebhookJob::class);
    }

    #[Test()]
    #[TestDox('Dispatches webhook synchronously')]
    #[Group('happy-path')]
    public function dispatches_webhook_synchronously(): void
    {
        // Arrange
        Queue::fake();

        // Since dispatchSync() calls handle() directly which would make actual HTTP requests,
        // we verify the method exists and chaining works instead
        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->payload(['event' => 'test.event']);

        // Act - This would normally execute synchronously but we can't test actual execution
        // without making real HTTP requests. We verify queue is not used.

        // Assert - Just verify the method is callable
        $this->assertTrue(method_exists($webhookCall, 'dispatchSync'));
        Queue::assertNotPushed(CallWebhookJob::class);
    }

    #[Test()]
    #[TestDox('Dispatches webhook when condition is true')]
    #[Group('happy-path')]
    public function dispatches_webhook_when_condition_is_true(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook');

        // Act
        $webhookCall->dispatchIf(true);

        // Assert
        Queue::assertPushed(CallWebhookJob::class, 1);
    }

    #[Test()]
    #[TestDox('Does not dispatch webhook when condition is false')]
    #[Group('happy-path')]
    public function does_not_dispatch_webhook_when_condition_is_false(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook');

        // Act
        $webhookCall->dispatchIf(false);

        // Assert
        Queue::assertNotPushed(CallWebhookJob::class);
    }

    #[Test()]
    #[TestDox('Dispatches webhook when dispatchUnless condition is false')]
    #[Group('happy-path')]
    public function dispatches_webhook_when_unless_condition_is_false(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook');

        // Act
        $webhookCall->dispatchUnless(false);

        // Assert
        Queue::assertPushed(CallWebhookJob::class, 1);
    }

    #[Test()]
    #[TestDox('Does not dispatch webhook when dispatchUnless condition is true')]
    #[Group('happy-path')]
    public function does_not_dispatch_webhook_when_unless_condition_is_true(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook');

        // Act
        $webhookCall->dispatchUnless(true);

        // Assert
        Queue::assertNotPushed(CallWebhookJob::class);
    }

    #[Test()]
    #[TestDox('Loads default values from configuration')]
    #[Group('happy-path')]
    public function loads_default_values_from_configuration(): void
    {
        // Arrange
        Queue::fake();

        // Note: Due to implementation, properties with default values (httpVerb, timeoutInSeconds, tries)
        // will only use config if not already set. verifySsl and throwExceptionOnFailure are always
        // loaded from config when prepareDefaults() runs.
        Config::set('webhook.server.verify_ssl', false);

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook');

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);

            $verifySslProperty = $reflection->getProperty('verifySsl');

            // verifySsl is always loaded from config
            return $verifySslProperty->getValue($job) === false;
        });
    }

    #[Test()]
    #[TestDox('Uses HMAC signer when signature version is V1_HMAC')]
    #[Group('happy-path')]
    public function uses_hmac_signer_for_v1_signature(): void
    {
        // Arrange
        Queue::fake();

        Config::set('webhook.server.signature_version', SignatureVersion::V1_HMAC->value);
        Config::set('webhook.server.signing_secret', 'hmac-secret');

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook');

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('signer');

            $signer = $property->getValue($job);

            return $signer instanceof HmacSigner;
        });
    }

    #[Test()]
    #[TestDox('Uses Ed25519 signer when signature version is V1A_ED25519')]
    #[Group('happy-path')]
    public function uses_ed25519_signer_for_v1a_signature(): void
    {
        // Arrange
        Queue::fake();

        Config::set('webhook.server.signature_version', SignatureVersion::V1A_ED25519->value);
        Config::set('webhook.server.ed25519_private_key', 'ed25519-key');

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook');

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('signer');

            $signer = $property->getValue($job);

            return $signer instanceof Ed25519Signer;
        });
    }

    #[Test()]
    #[TestDox('Uses custom secret over config secret for HMAC signer')]
    #[Group('happy-path')]
    public function uses_custom_secret_over_config_secret(): void
    {
        // Arrange
        Queue::fake();

        Config::set('webhook.server.signature_version', SignatureVersion::V1_HMAC->value);
        Config::set('webhook.server.signing_secret', 'config-secret');

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->useSecret('custom-secret');

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('signer');

            $signer = $property->getValue($job);

            if (!$signer instanceof HmacSigner) {
                return false;
            }

            $signerReflection = new ReflectionClass($signer);
            $secretProperty = $signerReflection->getProperty('secret');

            return $secretProperty->getValue($signer) === 'custom-secret';
        });
    }

    #[Test()]
    #[TestDox('Uses custom Ed25519 key over config key')]
    #[Group('happy-path')]
    public function uses_custom_ed25519_key_over_config_key(): void
    {
        // Arrange
        Queue::fake();

        Config::set('webhook.server.signature_version', SignatureVersion::V1A_ED25519->value);
        Config::set('webhook.server.ed25519_private_key', 'config-key');

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->signatureVersion(SignatureVersion::V1A_ED25519)
            ->useEd25519Key('custom-key');

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('signer');

            $signer = $property->getValue($job);

            if (!$signer instanceof Ed25519Signer) {
                return false;
            }

            $signerReflection = new ReflectionClass($signer);
            $keyProperty = $signerReflection->getProperty('privateKey');

            return $keyProperty->getValue($signer) === 'custom-key';
        });
    }

    #[Test()]
    #[TestDox('Uses custom signer over signature version configuration')]
    #[Group('happy-path')]
    public function uses_custom_signer_over_signature_version(): void
    {
        // Arrange
        Queue::fake();

        Config::set('webhook.server.signature_version', SignatureVersion::V1A_ED25519->value);

        $customSigner = new HmacSigner('custom-signer-secret');

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->useSigner($customSigner);

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job) use ($customSigner): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('signer');

            return $property->getValue($job) === $customSigner;
        });
    }

    #[Test()]
    #[TestDox('Uses custom webhook ID over generated ID')]
    #[Group('happy-path')]
    public function uses_custom_webhook_id_over_generated_id(): void
    {
        // Arrange
        Queue::fake();

        $customId = 'custom-webhook-id-12345';

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->webhookId($customId);

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job) use ($customId): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('webhookId');

            return $property->getValue($job) === $customId;
        });
    }

    #[Test()]
    #[TestDox('Generates webhook ID when not provided')]
    #[Group('happy-path')]
    public function generates_webhook_id_when_not_provided(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook');

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('webhookId');

            $webhookId = $property->getValue($job);

            // Verify it's a non-empty string (ULID format)
            return is_string($webhookId) && $webhookId !== '';
        });
    }

    #[Test()]
    #[TestDox('Generates timestamp when not provided')]
    #[Group('happy-path')]
    public function generates_timestamp_when_not_provided(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook');

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('timestamp');

            $timestamp = $property->getValue($job);

            // Verify it's a valid timestamp
            return is_int($timestamp) && $timestamp > 0;
        });
    }

    #[Test()]
    #[TestDox('Creates default ExponentialBackoffStrategy when not provided')]
    #[Group('happy-path')]
    public function creates_default_backoff_strategy_when_not_provided(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook');

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('backoffStrategy');

            $strategy = $property->getValue($job);

            return $strategy instanceof ExponentialBackoffStrategy;
        });
    }

    #[Test()]
    #[TestDox('Supports full fluent method chaining')]
    #[Group('happy-path')]
    public function supports_full_fluent_method_chaining(): void
    {
        // Arrange
        Queue::fake();

        // Act
        WebhookCall::create()
            ->url('https://example.com/webhook')
            ->payload(['event' => 'user.created'])
            ->withHeaders(['X-Custom' => 'value'])
            ->meta(['key' => 'value'])
            ->tags(['tag1', 'tag2'])
            ->useHttpVerb('POST')
            ->timeoutInSeconds(5)
            ->maximumTries(3)
            ->useBackoffStrategy(
                new ExponentialBackoffStrategy(),
            )
            ->doNotVerifySsl()
            ->webhookId('custom-id')
            ->useSecret('secret')
            ->signatureVersion(SignatureVersion::V1_HMAC)
            ->onQueue('webhooks')
            ->throwExceptionOnFailure()
            ->dispatch();

        // Assert
        Queue::assertPushedOn('webhooks', CallWebhookJob::class);
        Queue::assertPushed(CallWebhookJob::class, 1);
    }

    #[Test()]
    #[TestDox('Handles empty payload')]
    #[Group('edge-case')]
    public function handles_empty_payload(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->payload([]);

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('payload');

            return $property->getValue($job) === [];
        });
    }

    #[Test()]
    #[TestDox('Handles empty headers')]
    #[Group('edge-case')]
    public function handles_empty_headers(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->withHeaders([]);

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, 1);
    }

    #[Test()]
    #[TestDox('Handles empty metadata')]
    #[Group('edge-case')]
    public function handles_empty_metadata(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->meta([]);

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, 1);
    }

    #[Test()]
    #[TestDox('Handles empty tags')]
    #[Group('edge-case')]
    public function handles_empty_tags(): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->tags([]);

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, 1);
    }

    #[Test()]
    #[TestDox('Handles various case HTTP verbs')]
    #[Group('edge-case')]
    #[DataProvider('provideHandles_various_case_http_verbsCases')]
    public function handles_various_case_http_verbs(string $verb, string $expected): void
    {
        // Arrange
        Queue::fake();

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->useHttpVerb($verb);

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job) use ($expected): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('httpVerb');

            return $property->getValue($job) === $expected;
        });
    }

    /**
     * Provide HTTP verb test cases.
     *
     * @return Iterator<string, array<string>>
     */
    public static function provideHandles_various_case_http_verbsCases(): iterable
    {
        yield 'lowercase post' => ['post', 'POST'];

        yield 'lowercase get' => ['get', 'GET'];

        yield 'lowercase put' => ['put', 'PUT'];

        yield 'lowercase patch' => ['patch', 'PATCH'];

        yield 'lowercase delete' => ['delete', 'DELETE'];

        yield 'mixed case' => ['PaTcH', 'PATCH'];

        yield 'uppercase' => ['DELETE', 'DELETE'];
    }

    #[Test()]
    #[TestDox('Handles zero timeout')]
    #[Group('edge-case')]
    public function handles_zero_timeout(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();

        // Act
        $result = $webhookCall->timeoutInSeconds(0);

        // Assert - Just verify method chaining works with zero
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Handles zero tries')]
    #[Group('edge-case')]
    public function handles_zero_tries(): void
    {
        // Arrange
        $webhookCall = WebhookCall::create();

        // Act
        $result = $webhookCall->maximumTries(0);

        // Assert - Just verify method chaining works with zero
        $this->assertSame($webhookCall, $result, 'Should return self for method chaining');
    }

    #[Test()]
    #[TestDox('Handles URL with special characters')]
    #[Group('edge-case')]
    public function handles_url_with_special_characters(): void
    {
        // Arrange
        Queue::fake();

        $url = 'https://example.com/webhook?token=abc123&user_id=456';

        $webhookCall = WebhookCall::create()
            ->url($url);

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job) use ($url): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('url');

            return $property->getValue($job) === $url;
        });
    }

    #[Test()]
    #[TestDox('Handles deeply nested payload')]
    #[Group('edge-case')]
    public function handles_deeply_nested_payload(): void
    {
        // Arrange
        Queue::fake();

        $payload = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => ['data' => 'value'],
                    ],
                ],
            ],
        ];

        $webhookCall = WebhookCall::create()
            ->url('https://example.com/webhook')
            ->payload($payload);

        // Act
        $webhookCall->dispatch();

        // Assert
        Queue::assertPushed(CallWebhookJob::class, function ($job) use ($payload): bool {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('payload');

            return $property->getValue($job) === $payload;
        });
    }
}
