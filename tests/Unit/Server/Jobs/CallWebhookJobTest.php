<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Server\Jobs;

use Cline\Webhook\Server\Events\DispatchingWebhookCallEvent;
use Cline\Webhook\Server\Events\FinalWebhookCallFailedEvent;
use Cline\Webhook\Server\Events\WebhookCallFailedEvent;
use Cline\Webhook\Server\Events\WebhookCallSucceededEvent;
use Cline\Webhook\Server\Exceptions\MaxRetriesExceededException;
use Cline\Webhook\Server\Exceptions\WebhookCallException;
use Cline\Webhook\Server\Jobs\CallWebhookJob;
use Cline\Webhook\Server\Signers\HmacSigner;
use Cline\Webhook\Server\Strategies\ExponentialBackoffStrategy;
use Error;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use const JSON_THROW_ON_ERROR;

use function array_unique;
use function base64_encode;
use function beforeEach;
use function count;
use function describe;
use function expect;
use function hash_hmac;
use function json_decode;
use function json_encode;
use function method_exists;
use function serialize;
use function sprintf;
use function test;
use function unserialize;

/**
 * @internal
 */
describe('CallWebhookJob', function (): void {
    beforeEach(function (): void {
        // Set up common test fixtures
        $this->webhookId = 'webhook_123';
        $this->url = 'https://httpbin.org/status/200';
        $this->httpVerb = 'POST';
        $this->payload = ['event' => 'user.created', 'data' => ['id' => 123]];
        $this->headers = ['X-Custom-Header' => 'value'];
        $this->meta = ['source' => 'test'];
        $this->tags = ['test'];
        $this->secret = 'secret_key_123';
        $this->timestamp = 1_640_000_000;
        $this->tries = 3;
        $this->timeoutInSeconds = 5;

        // Create signer and backoff strategy
        $this->signer = new HmacSigner($this->secret);
        $this->backoffStrategy = new ExponentialBackoffStrategy(baseDelaySeconds: 1, maxDelaySeconds: 60, useJitter: false);

        // Fake events
        Event::fake();
    });

    describe('Job Structure', function (): void {
        test('implements ShouldQueue interface', function (): void {
            // Arrange & Act
            $job = createJob();

            // Assert
            expect($job)->toBeInstanceOf(ShouldQueue::class);
        });

        test('has Dispatchable trait', function (): void {
            // Arrange & Act
            $job = createJob();

            // Assert
            expect(method_exists($job, 'dispatch'))->toBeTrue();
            expect(method_exists($job, 'dispatchIf'))->toBeTrue();
            expect(method_exists($job, 'dispatchUnless'))->toBeTrue();
        });

        test('has InteractsWithQueue trait', function (): void {
            // Arrange & Act
            $job = createJob();

            // Assert
            expect(method_exists($job, 'delete'))->toBeTrue();
            expect(method_exists($job, 'release'))->toBeTrue();
            expect(method_exists($job, 'fail'))->toBeTrue();
        });

        test('has Queueable trait methods', function (): void {
            // Arrange
            $job = createJob();

            // Act & Assert
            expect(method_exists($job, 'onQueue'))->toBeTrue();
            expect(method_exists($job, 'onConnection'))->toBeTrue();
            expect(method_exists($job, 'delay'))->toBeTrue();
        });

        test('has SerializesModels trait', function (): void {
            // Arrange & Act
            $job = createJob();

            // Assert - Verify the job can be serialized (required for queuing)
            $serialized = serialize($job);
            expect($serialized)->toBeString();

            $unserialized = unserialize($serialized);
            expect($unserialized)->toBeInstanceOf(CallWebhookJob::class);
        });

        test('exposes tries property publicly', function (): void {
            // Arrange
            $job = createJob(tries: 5);

            // Act & Assert
            expect($job->tries)->toBe(5);
        });
    });

    describe('Tags for Horizon', function (): void {
        test('returns default tags including webhook identifier', function (): void {
            // Arrange
            $job = createJob(tags: []);

            // Act
            $tags = $job->tags();

            // Assert
            expect($tags)->toContain('webhook');
            expect($tags)->toContain('webhook:'.$this->webhookId);
        });

        test('merges custom tags with default tags', function (): void {
            // Arrange
            $customTags = ['priority', 'user-123'];
            $job = createJob(tags: $customTags);

            // Act
            $tags = $job->tags();

            // Assert
            expect($tags)->toContain('priority');
            expect($tags)->toContain('user-123');
            expect($tags)->toContain('webhook');
            expect($tags)->toContain('webhook:'.$this->webhookId);
        });

        test('preserves tag order with custom first', function (): void {
            // Arrange
            $customTags = ['custom1', 'custom2'];
            $job = createJob(tags: $customTags);

            // Act
            $tags = $job->tags();

            // Assert
            expect($tags[0])->toBe('custom1');
            expect($tags[1])->toBe('custom2');
        });

        test('handles empty custom tags', function (): void {
            // Arrange
            $job = createJob(tags: []);

            // Act
            $tags = $job->tags();

            // Assert
            expect($tags)->toHaveCount(2); // Only webhook tags
            expect($tags)->toContain('webhook');
            expect($tags)->toContain('webhook:'.$this->webhookId);
        });
    });

    describe('Backoff Strategy', function (): void {
        test('calculates backoff delay correctly', function (): void {
            // Arrange
            $job = createJob(tries: 5);

            // Simulate being on first attempt
            setJobAttempts($job, 1);

            // Act
            $backoff = $job->backoff();

            // Assert - First attempt: 1 * 2^0 = 1 second
            expect($backoff)->toBe(1);
        });

        test('backoff increases with attempt number', function (): void {
            // Arrange
            $job = createJob(tries: 5);

            // Act & Assert different attempts
            setJobAttempts($job, 1);
            expect($job->backoff())->toBe(1);  // 1 * 2^0 = 1

            setJobAttempts($job, 2);
            expect($job->backoff())->toBe(2);  // 1 * 2^1 = 2

            setJobAttempts($job, 3);
            expect($job->backoff())->toBe(4);  // 1 * 2^2 = 4

            setJobAttempts($job, 4);
            expect($job->backoff())->toBe(8);  // 1 * 2^3 = 8
        });

        test('uses custom backoff strategy', function (): void {
            // Arrange
            $customStrategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 5,
                maxDelaySeconds: 100,
                useJitter: false,
            );

            $job = new CallWebhookJob(
                webhookId: $this->webhookId,
                url: $this->url,
                httpVerb: $this->httpVerb,
                payload: $this->payload,
                headers: $this->headers,
                meta: $this->meta,
                tags: $this->tags,
                signer: $this->signer,
                timestamp: $this->timestamp,
                tries: $this->tries,
                backoffStrategy: $customStrategy,
                timeoutInSeconds: $this->timeoutInSeconds,
                verifySsl: true,
                throwExceptionOnFailure: false,
            );

            setJobAttempts($job, 1);

            // Act
            $backoff = $job->backoff();

            // Assert - Should use custom base delay
            expect($backoff)->toBe(5);
        });
    });

    describe('Exponential Backoff Strategy', function (): void {
        test('calculates delay with exponential growth', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 2,
                maxDelaySeconds: 60,
                useJitter: false,
            );

            // Act & Assert
            expect($strategy->calculate(1))->toBe(2);   // 2 * 2^0 = 2
            expect($strategy->calculate(2))->toBe(4);   // 2 * 2^1 = 4
            expect($strategy->calculate(3))->toBe(8);   // 2 * 2^2 = 8
            expect($strategy->calculate(4))->toBe(16);  // 2 * 2^3 = 16
            expect($strategy->calculate(5))->toBe(32);  // 2 * 2^4 = 32
        });

        test('caps delay at maximum', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 2,
                maxDelaySeconds: 60,
                useJitter: false,
            );

            // Act & Assert - Should cap at 60 seconds
            expect($strategy->calculate(10))->toBe(60);  // Would be 1024, capped at 60
            expect($strategy->calculate(20))->toBe(60);  // Would be huge, capped at 60
        });

        test('adds jitter when enabled', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 10,
                maxDelaySeconds: 100,
                useJitter: true,
            );

            // Act - Calculate same attempt multiple times
            $delays = [];

            for ($i = 0; $i < 20; ++$i) {
                $delays[] = $strategy->calculate(3);  // Base would be 40
            }

            // Assert - With jitter, delays should vary
            $uniqueDelays = array_unique($delays);
            expect(count($uniqueDelays))->toBeGreaterThan(1);

            // All delays should be in range [40, 50] (40 + 25% jitter)
            foreach ($delays as $delay) {
                expect($delay)->toBeGreaterThanOrEqual(40);
                expect($delay)->toBeLessThanOrEqual(50);
            }
        });

        test('does not add jitter when disabled', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 10,
                maxDelaySeconds: 100,
                useJitter: false,
            );

            // Act - Calculate same attempt multiple times
            $delays = [];

            for ($i = 0; $i < 10; ++$i) {
                $delays[] = $strategy->calculate(3);
            }

            // Assert - Without jitter, all delays should be identical
            $uniqueDelays = array_unique($delays);
            expect($uniqueDelays)->toHaveCount(1);
            expect($delays[0])->toBe(40);  // 10 * 2^2 = 40
        });

        test('handles first attempt correctly', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 3,
                maxDelaySeconds: 60,
                useJitter: false,
            );

            // Act & Assert - First attempt should just be base delay
            expect($strategy->calculate(1))->toBe(3);  // 3 * 2^0 = 3
        });

        test('uses default values when not specified', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy();

            // Act & Assert - Defaults: base=1, max=3600, jitter=true
            $delay1 = $strategy->calculate(1);
            expect($delay1)->toBeGreaterThanOrEqual(1);   // Base is 1
            expect($delay1)->toBeLessThanOrEqual(2);      // 1 + 25% jitter

            // With jitter, the max is applied AFTER jitter is added
            // So the result can exceed max by up to 25%
            $delayLarge = $strategy->calculate(20);
            expect($delayLarge)->toBeLessThanOrEqual(4_500);  // 3600 + 25% jitter
        });
    });

    describe('HMAC Signature Generation', function (): void {
        test('generates signature in correct format', function (): void {
            // Arrange
            $signer = new HmacSigner('test_secret');

            // Act
            $signature = $signer->sign('webhook_1', 1_640_000_000, '{"test":true}');

            // Assert
            expect($signature)->toStartWith('v1,');
            expect($signature)->toMatch('/^v1,[A-Za-z0-9+\/=]+$/');
        });

        test('generates reproducible signatures', function (): void {
            // Arrange
            $signer = new HmacSigner('test_secret');

            // Act - Generate same signature twice
            $signature1 = $signer->sign('webhook_1', 1_640_000_000, '{"test":true}');
            $signature2 = $signer->sign('webhook_1', 1_640_000_000, '{"test":true}');

            // Assert - Should be identical
            expect($signature1)->toBe($signature2);
        });

        test('signature changes with different webhook ID', function (): void {
            // Arrange
            $signer = new HmacSigner('test_secret');

            // Act
            $signature1 = $signer->sign('webhook_1', 1_640_000_000, '{"test":true}');
            $signature2 = $signer->sign('webhook_2', 1_640_000_000, '{"test":true}');

            // Assert
            expect($signature1)->not->toBe($signature2);
        });

        test('signature changes with different timestamp', function (): void {
            // Arrange
            $signer = new HmacSigner('test_secret');

            // Act
            $signature1 = $signer->sign('webhook_1', 1_640_000_000, '{"test":true}');
            $signature2 = $signer->sign('webhook_1', 1_640_000_001, '{"test":true}');

            // Assert
            expect($signature1)->not->toBe($signature2);
        });

        test('signature changes with different payload', function (): void {
            // Arrange
            $signer = new HmacSigner('test_secret');

            // Act
            $signature1 = $signer->sign('webhook_1', 1_640_000_000, '{"test":true}');
            $signature2 = $signer->sign('webhook_1', 1_640_000_000, '{"test":false}');

            // Assert
            expect($signature1)->not->toBe($signature2);
        });

        test('signature changes with different secret', function (): void {
            // Arrange
            $signer1 = new HmacSigner('secret_1');
            $signer2 = new HmacSigner('secret_2');

            // Act
            $signature1 = $signer1->sign('webhook_1', 1_640_000_000, '{"test":true}');
            $signature2 = $signer2->sign('webhook_1', 1_640_000_000, '{"test":true}');

            // Assert
            expect($signature1)->not->toBe($signature2);
        });

        test('matches expected signature format', function (): void {
            // Arrange
            $secret = 'my_secret';
            $webhookId = 'wh_123';
            $timestamp = 1_640_000_000;
            $payload = '{"event":"test"}';

            $signer = new HmacSigner($secret);

            // Calculate expected signature manually
            $signedContent = sprintf('%s.%d.%s', $webhookId, $timestamp, $payload);
            $expectedHash = hash_hmac('sha256', $signedContent, $secret, true);
            $expectedEncoded = 'v1,'.base64_encode($expectedHash);

            // Act
            $actualSignature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($actualSignature)->toBe($expectedEncoded);
        });
    });

    describe('Event Dispatching Patterns', function (): void {
        test('dispatches DispatchingWebhookCallEvent on successful call')->skip(
            'Requires real HTTP call or mock client injection',
        );

        test('dispatches WebhookCallSucceededEvent on 2xx response')->skip(
            'Requires real HTTP call or mock client injection',
        );

        test('dispatches WebhookCallFailedEvent on error')->skip(
            'Requires real HTTP call or mock client injection',
        );

        test('dispatches FinalWebhookCallFailedEvent after max retries')->skip(
            'Requires real HTTP call or mock client injection',
        );
    });

    describe('Exception Handling', function (): void {
        test('MaxRetriesExceededException includes retry count and URL', function (): void {
            // Arrange & Act
            $exception = MaxRetriesExceededException::make(3, 'https://example.com/webhook');

            // Assert
            expect($exception->getMessage())->toContain('3');
            expect($exception->getMessage())->toContain('https://example.com/webhook');
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        });

        test('WebhookCallException can be created for dispatch failure', function (): void {
            // Arrange
            $previous = new ConnectException('Connection refused', new Request('POST', 'https://example.com'));

            // Act
            $exception = WebhookCallException::dispatchFailed('https://example.com', $previous);

            // Assert
            expect($exception->getMessage())->toContain('https://example.com');
            expect($exception->getPrevious())->toBe($previous);
        });

        test('WebhookCallException can be created for HTTP error', function (): void {
            // Arrange & Act
            $exception = WebhookCallException::httpError(
                'https://example.com',
                500,
                'Internal Server Error',
            );

            // Assert
            expect($exception->getMessage())->toContain('https://example.com');
            expect($exception->getMessage())->toContain('500');
            expect($exception->getMessage())->toContain('Internal Server Error');
        });
    });

    describe('Failed Job Hook', function (): void {
        test('failed method does not throw exception', function (): void {
            // Arrange
            $job = createJob();
            $exception = new Exception('Test exception');

            // Act & Assert - Should not throw
            $job->failed($exception);

            expect(true)->toBeTrue();
        });

        test('failed method accepts any Throwable', function (): void {
            // Arrange
            $job = createJob();
            $error = new Error('Test error');

            // Act & Assert - Should accept Error too
            $job->failed($error);

            expect(true)->toBeTrue();
        });
    });

    describe('Configuration Options', function (): void {
        test('accepts custom timeout in seconds', function (): void {
            // Arrange & Act
            $job = createJob(timeoutInSeconds: 120);

            // Assert
            expect($job)->toBeInstanceOf(CallWebhookJob::class);
        });

        test('accepts SSL verification enabled', function (): void {
            // Arrange & Act
            $job = createJob(verifySsl: true);

            // Assert
            expect($job)->toBeInstanceOf(CallWebhookJob::class);
        });

        test('accepts SSL verification disabled', function (): void {
            // Arrange & Act
            $job = createJob(verifySsl: false);

            // Assert
            expect($job)->toBeInstanceOf(CallWebhookJob::class);
        });

        test('accepts throwExceptionOnFailure enabled', function (): void {
            // Arrange & Act
            $job = createJob(throwExceptionOnFailure: true);

            // Assert
            expect($job)->toBeInstanceOf(CallWebhookJob::class);
        });

        test('accepts throwExceptionOnFailure disabled', function (): void {
            // Arrange & Act
            $job = createJob(throwExceptionOnFailure: false);

            // Assert
            expect($job)->toBeInstanceOf(CallWebhookJob::class);
        });

        test('accepts custom HTTP verbs', function (): void {
            // Arrange & Act
            $verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

            foreach ($verbs as $verb) {
                $job = createJob(httpVerb: $verb);
                expect($job)->toBeInstanceOf(CallWebhookJob::class);
            }
        });

        test('accepts meta data', function (): void {
            // Arrange & Act
            $meta = ['source' => 'api', 'version' => '1.0', 'user_id' => 123];
            $job = createJob(meta: $meta);

            // Assert
            expect($job)->toBeInstanceOf(CallWebhookJob::class);
        });

        test('accepts empty meta data', function (): void {
            // Arrange & Act
            $job = createJob(meta: []);

            // Assert
            expect($job)->toBeInstanceOf(CallWebhookJob::class);
        });

        test('accepts custom headers', function (): void {
            // Arrange & Act
            $headers = [
                'X-API-Key' => 'secret',
                'X-Request-ID' => 'req_123',
                'Authorization' => 'Bearer token',
            ];
            $job = createJob(headers: $headers);

            // Assert
            expect($job)->toBeInstanceOf(CallWebhookJob::class);
        });

        test('accepts complex payload structures', function (): void {
            // Arrange & Act
            $payload = [
                'event' => 'order.created',
                'data' => [
                    'order_id' => 123,
                    'customer' => [
                        'id' => 456,
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ],
                    'items' => [
                        ['id' => 1, 'quantity' => 2],
                        ['id' => 2, 'quantity' => 1],
                    ],
                ],
                'metadata' => [
                    'ip' => '192.168.1.1',
                    'user_agent' => 'Mozilla/5.0',
                ],
            ];

            $job = createJob(payload: $payload);

            // Assert
            expect($job)->toBeInstanceOf(CallWebhookJob::class);
        });

        test('accepts unicode in payload', function (): void {
            // Arrange & Act
            $payload = [
                'message' => 'Hello 世界 🌍',
                'emoji' => '👋',
                'japanese' => '日本語',
            ];

            $job = createJob(payload: $payload);

            // Assert
            expect($job)->toBeInstanceOf(CallWebhookJob::class);
        });
    });

    describe('Payload JSON Encoding', function (): void {
        test('encodes payload to JSON correctly', function (): void {
            // Arrange
            $payload = ['event' => 'test', 'value' => 123];

            // Act
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

            // Assert
            expect($encoded)->toBe('{"event":"test","value":123}');
        });

        test('handles empty payload', function (): void {
            // Arrange
            $payload = [];

            // Act
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

            // Assert - Empty array encodes to []
            expect($encoded)->toBe('[]');
        });

        test('handles nested arrays', function (): void {
            // Arrange
            $payload = [
                'data' => [
                    'nested' => ['value' => 1],
                ],
            ];

            // Act
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

            // Assert
            expect($encoded)->toBe('{"data":{"nested":{"value":1}}}');
        });

        test('handles unicode characters', function (): void {
            // Arrange
            $payload = ['message' => '世界'];

            // Act
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
            $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

            // Assert
            expect($decoded['message'])->toBe('世界');
        });
    });
});

/**
 * Helper function to create job with default test values.
 */
function createJob(
    ?string $webhookId = null,
    ?string $url = null,
    ?string $httpVerb = null,
    ?array $payload = null,
    ?array $headers = null,
    ?array $meta = null,
    ?array $tags = null,
    ?int $timestamp = null,
    ?int $tries = null,
    ?int $timeoutInSeconds = null,
    bool $verifySsl = true,
    bool $throwExceptionOnFailure = false,
): CallWebhookJob {
    $test = test();

    return new CallWebhookJob(
        webhookId: $webhookId ?? $test->webhookId,
        url: $url ?? $test->url,
        httpVerb: $httpVerb ?? $test->httpVerb,
        payload: $payload ?? $test->payload,
        headers: $headers ?? $test->headers,
        meta: $meta ?? $test->meta,
        tags: $tags ?? $test->tags,
        signer: $test->signer,
        timestamp: $timestamp ?? $test->timestamp,
        tries: $tries ?? $test->tries,
        backoffStrategy: $test->backoffStrategy,
        timeoutInSeconds: $timeoutInSeconds ?? $test->timeoutInSeconds,
        verifySsl: $verifySsl,
        throwExceptionOnFailure: $throwExceptionOnFailure,
    );
}

/**
 * Helper function to set job attempts via reflection.
 */
function setJobAttempts(CallWebhookJob $job, int $attempts): void
{
    $reflection = new ReflectionClass($job);

    // Create a mock job property with attempts
    $mockJob = new class($attempts)
    {
        public function __construct(
            private readonly int $attempts,
        ) {}

        public function attempts(): int
        {
            return $this->attempts;
        }
    };

    // Try to set the 'job' property if it exists
    try {
        $jobProperty = $reflection->getProperty('job');
        $jobProperty->setAccessible(true);
        $jobProperty->setValue($job, $mockJob);
    } catch (ReflectionException $e) {
        // Property doesn't exist yet (job not queued)
        // This is expected in unit tests
    }
}
