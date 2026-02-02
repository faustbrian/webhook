<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Webhook\Client\Events\WebhookProcessedEvent;
use Cline\Webhook\Client\Jobs\ProcessWebhookJob;
use Cline\Webhook\Client\Models\WebhookCall;
use Cline\Webhook\Enums\WebhookStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\Support\CapturingWebhookProcessor;
use Tests\Support\FailingWebhookProcessor;
use Tests\Support\SpecialCharsExceptionProcessor;
use Tests\Support\SuccessfulWebhookProcessor;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    SuccessfulWebhookProcessor::reset();
    CapturingWebhookProcessor::reset();
});

describe('Happy Paths', function (): void {
    test('marks webhook as processing when handle is called', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'status' => WebhookStatus::PENDING,
            'attempts' => 0,
        ]);

        Config::set('webhook.client.configs.default.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::PROCESSED)
            ->and($webhook->attempts)->toBe(1);
    });

    test('marks webhook as processed on successful processing', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'status' => WebhookStatus::PENDING,
        ]);

        Config::set('webhook.client.configs.default.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::PROCESSED)
            ->and($webhook->processed_at)->not()->toBeNull()
            ->and($webhook->exception)->toBeNull();
    });

    test('fires WebhookProcessedEvent on successful processing', function (): void {
        // Arrange
        Event::fake([WebhookProcessedEvent::class]);

        $webhook = WebhookCall::factory()->create();
        Config::set('webhook.client.configs.default.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        Event::assertDispatched(WebhookProcessedEvent::class, fn (WebhookProcessedEvent $event): bool => $event->webhookCall->id === $webhook->id);
    });

    test('processes webhook with no-op processor when no processor configured', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create();
        Config::set('webhook.client.configs.default.webhook_processor');

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::PROCESSED)
            ->and($webhook->processed_at)->not()->toBeNull();
    });

    test('increments attempts counter when processing', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'attempts' => 2,
        ]);

        Config::set('webhook.client.configs.default.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->attempts)->toBe(3);
    });

    test('clears exception message on successful processing after previous failure', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'status' => WebhookStatus::FAILED,
            'exception' => 'Previous failure message',
        ]);

        Config::set('webhook.client.configs.default.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->exception)->toBeNull();
    });
});

describe('Sad Paths', function (): void {
    test('marks webhook as failed when processing throws exception', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'status' => WebhookStatus::PENDING,
        ]);

        Config::set('webhook.client.configs.default.webhook_processor', FailingWebhookProcessor::class);

        // Act & Assert
        $job = new ProcessWebhookJob($webhook);

        try {
            $job->handle();
        } catch (RuntimeException) {
            // Expected exception
        }

        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::FAILED)
            ->and($webhook->exception)->toBe('Test processing failure');
    });

    test('stores exception message in webhook record when processing fails', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create();
        Config::set('webhook.client.configs.default.webhook_processor', FailingWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);

        try {
            $job->handle();
        } catch (RuntimeException) {
            // Expected exception
        }

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->exception)->toBe('Test processing failure');
    });

    test('rethrows exception after marking webhook as failed', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create();
        Config::set('webhook.client.configs.default.webhook_processor', FailingWebhookProcessor::class);

        // Act & Assert
        $job = new ProcessWebhookJob($webhook);

        expect(fn () => $job->handle())
            ->toThrow(RuntimeException::class, 'Test processing failure');
    });

    test('does not fire WebhookProcessedEvent when processing fails', function (): void {
        // Arrange
        Event::fake([WebhookProcessedEvent::class]);

        $webhook = WebhookCall::factory()->create();
        Config::set('webhook.client.configs.default.webhook_processor', FailingWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);

        try {
            $job->handle();
        } catch (RuntimeException) {
            // Expected exception
        }

        // Assert
        Event::assertNotDispatched(WebhookProcessedEvent::class);
    });

    test('failed method marks webhook as failed', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'status' => WebhookStatus::PROCESSING,
        ]);

        $exception = new RuntimeException('Job failed');
        $job = new ProcessWebhookJob($webhook);

        // Act
        $job->failed($exception);

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::FAILED)
            ->and($webhook->exception)->toBe('Job failed');
    });
});

describe('Edge Cases', function (): void {
    test('handles webhook with null headers', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'headers' => null,
        ]);

        Config::set('webhook.client.configs.default.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::PROCESSED);
    });

    test('handles webhook with empty payload', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'payload' => [],
        ]);

        Config::set('webhook.client.configs.default.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::PROCESSED);
    });

    test('handles webhook with custom config name', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'config_name' => 'custom',
        ]);

        Config::set('webhook.client.configs.custom.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::PROCESSED);
    });

    test('processes webhook that was already processing', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'status' => WebhookStatus::PROCESSING,
            'attempts' => 1,
        ]);

        Config::set('webhook.client.configs.default.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::PROCESSED)
            ->and($webhook->attempts)->toBe(2);
    });

    test('handles very large payload', function (): void {
        // Arrange
        $largePayload = [
            'data' => array_fill(0, 1_000, [
                'id' => fake()->uuid(),
                'name' => fake()->name(),
                'email' => fake()->email(),
                'description' => fake()->text(500),
            ]),
        ];

        $webhook = WebhookCall::factory()->create([
            'payload' => $largePayload,
        ]);

        Config::set('webhook.client.configs.default.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::PROCESSED);
    });

    test('handles unicode characters in payload', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'payload' => [
                'message' => '你好世界 🌍 Здравствуй мир',
                'emoji' => '🎉🎊🎈',
            ],
        ]);

        Config::set('webhook.client.configs.default.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::PROCESSED);
    });

    test('handles special characters in exception message', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create();

        Config::set('webhook.client.configs.default.webhook_processor', SpecialCharsExceptionProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);

        try {
            $job->handle();
        } catch (RuntimeException) {
            // Expected exception
        }

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->exception)->toBe('Error with special chars: <>&"\'');
    });

    test('handles multiple retry attempts incrementing attempts counter', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'attempts' => 0,
        ]);

        // Act - Simulate 3 processing attempts
        // First attempt
        Config::set('webhook.client.configs.default.webhook_processor', FailingWebhookProcessor::class);
        $job1 = new ProcessWebhookJob($webhook);

        try {
            $job1->handle();
        } catch (RuntimeException) {
            // Expected
        }

        // Second attempt (after failure)
        $webhook = WebhookCall::query()->find($webhook->id);
        $webhook->update(['status' => WebhookStatus::PENDING]);

        $job2 = new ProcessWebhookJob($webhook);

        try {
            $job2->handle();
        } catch (RuntimeException) {
            // Expected
        }

        // Third attempt succeeds
        $webhook = WebhookCall::query()->find($webhook->id);
        $webhook->update(['status' => WebhookStatus::PENDING]);
        Config::set('webhook.client.configs.default.webhook_processor', SuccessfulWebhookProcessor::class);
        $job3 = new ProcessWebhookJob($webhook);
        $job3->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->attempts)->toBe(3)
            ->and($webhook->status)->toBe(WebhookStatus::PROCESSED);
    });

    test('processor can access webhook payload and headers', function (): void {
        // Arrange
        $testPayload = ['test' => 'data', 'value' => 123];
        $testHeaders = ['x-custom-header' => 'custom-value'];

        $webhook = WebhookCall::factory()->create([
            'payload' => $testPayload,
            'headers' => $testHeaders,
        ]);

        Config::set('webhook.client.configs.default.webhook_processor', CapturingWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        expect(CapturingWebhookProcessor::$captured)->not()->toBeNull()
            ->and(CapturingWebhookProcessor::$captured->payload)->toBe($testPayload)
            ->and(CapturingWebhookProcessor::$captured->headers)->toBe($testHeaders);
    });
});

describe('Queue Integration', function (): void {
    test('job can be serialized and queued', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create();

        // Act
        $job = new ProcessWebhookJob($webhook);
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        // Assert
        expect($unserialized)->toBeInstanceOf(ProcessWebhookJob::class);
    });

    test('job uses correct queue traits', function (): void {
        // Arrange & Act
        $webhook = WebhookCall::factory()->create();
        $job = new ProcessWebhookJob($webhook);

        // Assert
        expect($job)->toBeInstanceOf(ShouldQueue::class);

        $reflection = new ReflectionClass($job);
        $traits = array_keys($reflection->getTraits());

        expect($traits)->toContain(
            Queueable::class,
            InteractsWithQueue::class,
            SerializesModels::class,
            Dispatchable::class,
        );
    });
});

describe('Processor Resolution', function (): void {
    test('uses configured processor for specific config', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'config_name' => 'stripe',
        ]);

        Config::set('webhook.client.configs.stripe.webhook_processor', SuccessfulWebhookProcessor::class);

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        expect(SuccessfulWebhookProcessor::$processed)->toBeTrue();
    });

    test('falls back to no-op processor when config has no processor', function (): void {
        // Arrange
        $webhook = WebhookCall::factory()->create([
            'config_name' => 'unconfigured',
        ]);

        Config::set('webhook.client.configs.unconfigured.webhook_processor');

        // Act
        $job = new ProcessWebhookJob($webhook);
        $job->handle();

        // Assert
        $webhook = WebhookCall::query()->find($webhook->id);
        expect($webhook->status)->toBe(WebhookStatus::PROCESSED)
            ->and(SuccessfulWebhookProcessor::$processed)->toBeFalse();
    });
});
