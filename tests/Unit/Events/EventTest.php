<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Events;

use Cline\Webhook\Client\Events\InvalidWebhookSignatureEvent;
use Cline\Webhook\Client\Events\WebhookProcessedEvent;
use Cline\Webhook\Client\Events\WebhookReceivedEvent;
use Cline\Webhook\Client\Models\WebhookCall;
use Cline\Webhook\Server\Events\DispatchingWebhookCallEvent;
use Cline\Webhook\Server\Events\FinalWebhookCallFailedEvent;
use Cline\Webhook\Server\Events\WebhookCallFailedEvent;
use Cline\Webhook\Server\Events\WebhookCallSucceededEvent;
use Exception;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use RuntimeException;

use function array_merge;
use function beforeEach;
use function class_uses;
use function describe;
use function expect;
use function is_array;
use function json_encode;
use function serialize;
use function str_repeat;
use function test;
use function unserialize;
use function uses;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Set up package configuration
    Config::set('webhook.primary_key_type', 'ulid');
    Config::set('webhook.client.configs.default.delete_after_days', 30);

    // Run migrations
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
});

/**
 * Helper function to create a webhook call for client event testing.
 */
function createWebhookCall(array $attributes = []): WebhookCall
{
    $defaults = [
        'id' => (string) Str::ulid(),
        'config_name' => 'default',
        'webhook_id' => 'wh_'.Str::random(10),
        'timestamp' => 1_703_001_600,
        'payload' => json_encode([]),
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => Date::now(),
        'updated_at' => Date::now(),
    ];

    $merged = array_merge($defaults, $attributes);

    // Convert arrays to JSON for payload and headers
    if (isset($merged['payload']) && is_array($merged['payload'])) {
        $merged['payload'] = json_encode($merged['payload']);
    }

    if (isset($merged['headers']) && is_array($merged['headers'])) {
        $merged['headers'] = json_encode($merged['headers']);
    }

    // Insert directly into database using query builder
    DB::table('webhook_calls')->insert($merged);

    // Return the model instance
    $webhookId = $merged['webhook_id'];

    return WebhookCall::query()->where('webhook_id', $webhookId)->firstOrFail();
}

describe('Server Events - DispatchingWebhookCallEvent', function (): void {
    test('constructs with correct properties', function (): void {
        // Arrange
        $webhookId = 'wh_123456';
        $url = 'https://example.com/webhook';
        $payload = ['event' => 'user.created', 'data' => ['id' => 1]];
        $headers = ['X-Custom' => 'value', 'Content-Type' => 'application/json'];

        // Act
        $event = new DispatchingWebhookCallEvent($webhookId, $url, $payload, $headers);

        // Assert
        expect($event->webhookId)->toBe($webhookId)
            ->and($event->url)->toBe($url)
            ->and($event->payload)->toBe($payload)
            ->and($event->headers)->toBe($headers);
    });

    test('is readonly and immutable', function (): void {
        // Arrange
        $event = new DispatchingWebhookCallEvent('wh_123', 'https://example.com', [], []);

        // Act
        $reflection = new ReflectionClass($event);

        // Assert
        expect($reflection->isReadOnly())->toBeTrue();
    });

    test('uses dispatchable trait', function (): void {
        // Arrange & Act
        $traits = class_uses(DispatchingWebhookCallEvent::class);

        // Assert
        expect($traits)->toContain(Dispatchable::class);
    });

    test('uses serializes models trait', function (): void {
        // Arrange & Act
        $traits = class_uses(DispatchingWebhookCallEvent::class);

        // Assert
        expect($traits)->toContain(SerializesModels::class);
    });

    test('handles empty payload array', function (): void {
        // Arrange & Act
        $event = new DispatchingWebhookCallEvent('wh_123', 'https://example.com', [], []);

        // Assert
        expect($event->payload)->toBe([]);
    });

    test('handles empty headers array', function (): void {
        // Arrange & Act
        $event = new DispatchingWebhookCallEvent('wh_123', 'https://example.com', [], []);

        // Assert
        expect($event->headers)->toBe([]);
    });

    test('handles complex nested payload', function (): void {
        // Arrange
        $payload = [
            'event' => 'order.created',
            'data' => [
                'id' => 123,
                'items' => [
                    ['product_id' => 1, 'quantity' => 2],
                    ['product_id' => 2, 'quantity' => 1],
                ],
                'metadata' => [
                    'source' => 'web',
                    'user_agent' => 'Mozilla/5.0',
                ],
            ],
        ];

        // Act
        $event = new DispatchingWebhookCallEvent('wh_123', 'https://example.com', $payload, []);

        // Assert
        expect($event->payload)->toBe($payload);
    });

    test('handles unicode in payload', function (): void {
        // Arrange
        $payload = [
            'message' => 'Hello 世界 🌍',
            'emoji' => '😀🎉',
        ];

        // Act
        $event = new DispatchingWebhookCallEvent('wh_123', 'https://example.com', $payload, []);

        // Assert
        expect($event->payload)->toBe($payload);
    });

    test('handles special characters in headers', function (): void {
        // Arrange
        $headers = [
            'X-Custom-Header' => 'value with spaces',
            'X-Signature' => 'sha256=abc123def456',
        ];

        // Act
        $event = new DispatchingWebhookCallEvent('wh_123', 'https://example.com', [], $headers);

        // Assert
        expect($event->headers)->toBe($headers);
    });
});

describe('Server Events - WebhookCallSucceededEvent', function (): void {
    test('constructs with correct properties', function (): void {
        // Arrange
        $webhookId = 'wh_123456';
        $url = 'https://example.com/webhook';
        $statusCode = 200;
        $attempt = 1;

        // Act
        $event = new WebhookCallSucceededEvent($webhookId, $url, $statusCode, $attempt);

        // Assert
        expect($event->webhookId)->toBe($webhookId)
            ->and($event->url)->toBe($url)
            ->and($event->statusCode)->toBe($statusCode)
            ->and($event->attempt)->toBe($attempt);
    });

    test('is readonly and immutable', function (): void {
        // Arrange
        $event = new WebhookCallSucceededEvent('wh_123', 'https://example.com', 200, 1);

        // Act
        $reflection = new ReflectionClass($event);

        // Assert
        expect($reflection->isReadOnly())->toBeTrue();
    });

    test('uses dispatchable trait', function (): void {
        // Arrange & Act
        $traits = class_uses(WebhookCallSucceededEvent::class);

        // Assert
        expect($traits)->toContain(Dispatchable::class);
    });

    test('uses serializes models trait', function (): void {
        // Arrange & Act
        $traits = class_uses(WebhookCallSucceededEvent::class);

        // Assert
        expect($traits)->toContain(SerializesModels::class);
    });

    test('handles various HTTP status codes', function (int $statusCode): void {
        // Arrange & Act
        $event = new WebhookCallSucceededEvent('wh_123', 'https://example.com', $statusCode, 1);

        // Assert
        expect($event->statusCode)->toBe($statusCode);
    })->with([200, 201, 202, 204]);

    test('handles multiple attempts', function (): void {
        // Arrange & Act
        $event = new WebhookCallSucceededEvent('wh_123', 'https://example.com', 200, 5);

        // Assert
        expect($event->attempt)->toBe(5);
    });

    test('handles first attempt', function (): void {
        // Arrange & Act
        $event = new WebhookCallSucceededEvent('wh_123', 'https://example.com', 200, 1);

        // Assert
        expect($event->attempt)->toBe(1);
    });
});

describe('Server Events - WebhookCallFailedEvent', function (): void {
    test('constructs with correct properties', function (): void {
        // Arrange
        $webhookId = 'wh_123456';
        $url = 'https://example.com/webhook';
        $attempt = 1;
        $exception = new RuntimeException('Connection timeout');

        // Act
        $event = new WebhookCallFailedEvent($webhookId, $url, $attempt, $exception);

        // Assert
        expect($event->webhookId)->toBe($webhookId)
            ->and($event->url)->toBe($url)
            ->and($event->attempt)->toBe($attempt)
            ->and($event->exception)->toBe($exception);
    });

    test('is readonly and immutable', function (): void {
        // Arrange
        $event = new WebhookCallFailedEvent('wh_123', 'https://example.com', 1, new Exception());

        // Act
        $reflection = new ReflectionClass($event);

        // Assert
        expect($reflection->isReadOnly())->toBeTrue();
    });

    test('uses dispatchable trait', function (): void {
        // Arrange & Act
        $traits = class_uses(WebhookCallFailedEvent::class);

        // Assert
        expect($traits)->toContain(Dispatchable::class);
    });

    test('uses serializes models trait', function (): void {
        // Arrange & Act
        $traits = class_uses(WebhookCallFailedEvent::class);

        // Assert
        expect($traits)->toContain(SerializesModels::class);
    });

    test('handles different exception types', function (): void {
        // Arrange
        $exception = new RuntimeException('Network error');

        // Act
        $event = new WebhookCallFailedEvent('wh_123', 'https://example.com', 1, $exception);

        // Assert
        expect($event->exception)->toBeInstanceOf(RuntimeException::class)
            ->and($event->exception->getMessage())->toBe('Network error');
    });

    test('handles exception with empty message', function (): void {
        // Arrange
        $exception = new Exception('');

        // Act
        $event = new WebhookCallFailedEvent('wh_123', 'https://example.com', 1, $exception);

        // Assert
        expect($event->exception->getMessage())->toBe('');
    });

    test('handles multiple attempts', function (): void {
        // Arrange & Act
        $event = new WebhookCallFailedEvent('wh_123', 'https://example.com', 3, new Exception());

        // Assert
        expect($event->attempt)->toBe(3);
    });

    test('preserves exception stack trace', function (): void {
        // Arrange
        $exception = new RuntimeException('Test error');

        // Act
        $event = new WebhookCallFailedEvent('wh_123', 'https://example.com', 1, $exception);

        // Assert
        expect($event->exception->getTrace())->toBeArray();
    });

    test('handles exception with unicode message', function (): void {
        // Arrange
        $exception = new RuntimeException('Error: 世界 🌍');

        // Act
        $event = new WebhookCallFailedEvent('wh_123', 'https://example.com', 1, $exception);

        // Assert
        expect($event->exception->getMessage())->toBe('Error: 世界 🌍');
    });
});

describe('Server Events - FinalWebhookCallFailedEvent', function (): void {
    test('constructs with correct properties', function (): void {
        // Arrange
        $webhookId = 'wh_123456';
        $url = 'https://example.com/webhook';
        $totalAttempts = 5;
        $lastException = new RuntimeException('All retries exhausted');

        // Act
        $event = new FinalWebhookCallFailedEvent($webhookId, $url, $totalAttempts, $lastException);

        // Assert
        expect($event->webhookId)->toBe($webhookId)
            ->and($event->url)->toBe($url)
            ->and($event->totalAttempts)->toBe($totalAttempts)
            ->and($event->lastException)->toBe($lastException);
    });

    test('is readonly and immutable', function (): void {
        // Arrange
        $event = new FinalWebhookCallFailedEvent('wh_123', 'https://example.com', 5, new Exception());

        // Act
        $reflection = new ReflectionClass($event);

        // Assert
        expect($reflection->isReadOnly())->toBeTrue();
    });

    test('uses dispatchable trait', function (): void {
        // Arrange & Act
        $traits = class_uses(FinalWebhookCallFailedEvent::class);

        // Assert
        expect($traits)->toContain(Dispatchable::class);
    });

    test('uses serializes models trait', function (): void {
        // Arrange & Act
        $traits = class_uses(FinalWebhookCallFailedEvent::class);

        // Assert
        expect($traits)->toContain(SerializesModels::class);
    });

    test('handles single attempt before final failure', function (): void {
        // Arrange & Act
        $event = new FinalWebhookCallFailedEvent('wh_123', 'https://example.com', 1, new Exception());

        // Assert
        expect($event->totalAttempts)->toBe(1);
    });

    test('handles maximum retry attempts', function (): void {
        // Arrange & Act
        $event = new FinalWebhookCallFailedEvent('wh_123', 'https://example.com', 10, new Exception());

        // Assert
        expect($event->totalAttempts)->toBe(10);
    });

    test('preserves last exception details', function (): void {
        // Arrange
        $exception = new RuntimeException('Final retry failed');

        // Act
        $event = new FinalWebhookCallFailedEvent('wh_123', 'https://example.com', 5, $exception);

        // Assert
        expect($event->lastException)->toBeInstanceOf(RuntimeException::class)
            ->and($event->lastException->getMessage())->toBe('Final retry failed');
    });

    test('handles different exception types', function (): void {
        // Arrange
        $exception = new Exception('Generic error');

        // Act
        $event = new FinalWebhookCallFailedEvent('wh_123', 'https://example.com', 3, $exception);

        // Assert
        expect($event->lastException)->toBeInstanceOf(Exception::class);
    });
});

describe('Client Events - WebhookReceivedEvent', function (): void {
    test('constructs with correct properties', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'webhook_id' => 'wh_received',
            'payload' => ['event' => 'user.created'],
        ]);
        $configName = 'default';

        // Act
        $event = new WebhookReceivedEvent($webhookCall, $configName);

        // Assert
        expect($event->webhookCall)->toBe($webhookCall)
            ->and($event->configName)->toBe($configName);
    });

    test('is readonly and immutable', function (): void {
        // Arrange
        $webhookCall = createWebhookCall();
        $event = new WebhookReceivedEvent($webhookCall, 'default');

        // Act
        $reflection = new ReflectionClass($event);

        // Assert
        expect($reflection->isReadOnly())->toBeTrue();
    });

    test('uses dispatchable trait', function (): void {
        // Arrange & Act
        $traits = class_uses(WebhookReceivedEvent::class);

        // Assert
        expect($traits)->toContain(Dispatchable::class);
    });

    test('uses serializes models trait', function (): void {
        // Arrange & Act
        $traits = class_uses(WebhookReceivedEvent::class);

        // Assert
        expect($traits)->toContain(SerializesModels::class);
    });

    test('handles custom config names', function (): void {
        // Arrange
        $webhookCall = createWebhookCall(['config_name' => 'custom']);
        $configName = 'custom';

        // Act
        $event = new WebhookReceivedEvent($webhookCall, $configName);

        // Assert
        expect($event->configName)->toBe('custom');
    });

    test('webhook call properties are accessible', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'webhook_id' => 'wh_test',
            'payload' => ['data' => 'value'],
        ]);

        // Act
        $event = new WebhookReceivedEvent($webhookCall, 'default');

        // Assert
        expect($event->webhookCall->webhook_id)->toBe('wh_test')
            ->and($event->webhookCall->payload)->toBe(['data' => 'value']);
    });

    test('handles webhook with complex payload', function (): void {
        // Arrange
        $payload = [
            'event' => 'order.created',
            'data' => [
                'id' => 123,
                'items' => [
                    ['product_id' => 1, 'quantity' => 2],
                ],
            ],
        ];
        $webhookCall = createWebhookCall(['payload' => $payload]);

        // Act
        $event = new WebhookReceivedEvent($webhookCall, 'default');

        // Assert
        expect($event->webhookCall->payload)->toBe($payload);
    });
});

describe('Client Events - WebhookProcessedEvent', function (): void {
    test('constructs with correct properties', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'webhook_id' => 'wh_processed',
            'status' => 'processed',
        ]);

        // Act
        $event = new WebhookProcessedEvent($webhookCall);

        // Assert
        expect($event->webhookCall)->toBe($webhookCall);
    });

    test('is readonly and immutable', function (): void {
        // Arrange
        $webhookCall = createWebhookCall();
        $event = new WebhookProcessedEvent($webhookCall);

        // Act
        $reflection = new ReflectionClass($event);

        // Assert
        expect($reflection->isReadOnly())->toBeTrue();
    });

    test('uses dispatchable trait', function (): void {
        // Arrange & Act
        $traits = class_uses(WebhookProcessedEvent::class);

        // Assert
        expect($traits)->toContain(Dispatchable::class);
    });

    test('uses serializes models trait', function (): void {
        // Arrange & Act
        $traits = class_uses(WebhookProcessedEvent::class);

        // Assert
        expect($traits)->toContain(SerializesModels::class);
    });

    test('webhook call properties are accessible', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'webhook_id' => 'wh_test',
            'config_name' => 'custom',
        ]);

        // Act
        $event = new WebhookProcessedEvent($webhookCall);

        // Assert
        expect($event->webhookCall->webhook_id)->toBe('wh_test')
            ->and($event->webhookCall->config_name)->toBe('custom');
    });

    test('handles webhook with processed_at timestamp', function (): void {
        // Arrange
        $now = Date::now();
        $webhookCall = createWebhookCall(['processed_at' => $now]);

        // Act
        $event = new WebhookProcessedEvent($webhookCall);

        // Assert
        expect($event->webhookCall->processed_at)->not->toBeNull();
    });
});

describe('Client Events - InvalidWebhookSignatureEvent', function (): void {
    test('constructs with correct properties', function (): void {
        // Arrange
        $request = Request::create('https://example.com/webhook', \Symfony\Component\HttpFoundation\Request::METHOD_POST, ['event' => 'test']);
        $configName = 'default';

        // Act
        $event = new InvalidWebhookSignatureEvent($request, $configName);

        // Assert
        expect($event->request)->toBe($request)
            ->and($event->configName)->toBe($configName);
    });

    test('is readonly and immutable', function (): void {
        // Arrange
        $request = Request::create('https://example.com/webhook', \Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $event = new InvalidWebhookSignatureEvent($request, 'default');

        // Act
        $reflection = new ReflectionClass($event);

        // Assert
        expect($reflection->isReadOnly())->toBeTrue();
    });

    test('uses dispatchable trait', function (): void {
        // Arrange & Act
        $traits = class_uses(InvalidWebhookSignatureEvent::class);

        // Assert
        expect($traits)->toContain(Dispatchable::class);
    });

    test('uses serializes models trait', function (): void {
        // Arrange & Act
        $traits = class_uses(InvalidWebhookSignatureEvent::class);

        // Assert
        expect($traits)->toContain(SerializesModels::class);
    });

    test('request properties are accessible', function (): void {
        // Arrange
        $request = Request::create('https://example.com/webhook', \Symfony\Component\HttpFoundation\Request::METHOD_POST, ['data' => 'value']);

        // Act
        $event = new InvalidWebhookSignatureEvent($request, 'default');

        // Assert
        expect($event->request->method())->toBe('POST')
            ->and($event->request->input('data'))->toBe('value');
    });

    test('handles custom config names', function (): void {
        // Arrange
        $request = Request::create('https://example.com/webhook', \Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $configName = 'stripe';

        // Act
        $event = new InvalidWebhookSignatureEvent($request, $configName);

        // Assert
        expect($event->configName)->toBe('stripe');
    });

    test('handles request with headers', function (): void {
        // Arrange
        $request = Request::create('https://example.com/webhook', \Symfony\Component\HttpFoundation\Request::METHOD_POST);
        $request->headers->set('X-Signature', 'invalid_signature');

        // Act
        $event = new InvalidWebhookSignatureEvent($request, 'default');

        // Assert
        expect($event->request->header('X-Signature'))->toBe('invalid_signature');
    });

    test('handles request with json payload', function (): void {
        // Arrange
        $request = Request::create(
            'https://example.com/webhook',
            \Symfony\Component\HttpFoundation\Request::METHOD_POST,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['event' => 'user.created']),
        );

        // Act
        $event = new InvalidWebhookSignatureEvent($request, 'default');

        // Assert
        expect($event->request->getContent())->toContain('user.created');
    });

    test('handles request with unicode data', function (): void {
        // Arrange
        $request = Request::create('https://example.com/webhook', \Symfony\Component\HttpFoundation\Request::METHOD_POST, [
            'message' => 'Hello 世界 🌍',
        ]);

        // Act
        $event = new InvalidWebhookSignatureEvent($request, 'default');

        // Assert
        expect($event->request->input('message'))->toBe('Hello 世界 🌍');
    });
});

describe('Event Serialization', function (): void {
    test('server events can be serialized', function (): void {
        // Arrange
        $event = new DispatchingWebhookCallEvent('wh_123', 'https://example.com', [], []);

        // Act
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        // Assert
        expect($unserialized)->toBeInstanceOf(DispatchingWebhookCallEvent::class)
            ->and($unserialized->webhookId)->toBe('wh_123');
    });

    test('webhook call succeeded event can be serialized', function (): void {
        // Arrange
        $event = new WebhookCallSucceededEvent('wh_123', 'https://example.com', 200, 1);

        // Act
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        // Assert
        expect($unserialized)->toBeInstanceOf(WebhookCallSucceededEvent::class)
            ->and($unserialized->statusCode)->toBe(200);
    });

    test('client events with webhook call can be serialized', function (): void {
        // Arrange
        $webhookCall = createWebhookCall(['webhook_id' => 'wh_serialize']);
        $event = new WebhookReceivedEvent($webhookCall, 'default');

        // Act
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        // Assert
        expect($unserialized)->toBeInstanceOf(WebhookReceivedEvent::class)
            ->and($unserialized->configName)->toBe('default');
    });
});

describe('Edge Cases - All Events', function (): void {
    test('handles very long webhook IDs', function (): void {
        // Arrange
        $longId = 'wh_'.str_repeat('a', 1_000);

        // Act
        $event = new DispatchingWebhookCallEvent($longId, 'https://example.com', [], []);

        // Assert
        expect($event->webhookId)->toBe($longId);
    });

    test('handles very long URLs', function (): void {
        // Arrange
        $longUrl = 'https://example.com/'.str_repeat('path/', 100);

        // Act
        $event = new WebhookCallSucceededEvent('wh_123', $longUrl, 200, 1);

        // Assert
        expect($event->url)->toBe($longUrl);
    });

    test('handles zero attempts', function (): void {
        // Arrange & Act
        $event = new WebhookCallSucceededEvent('wh_123', 'https://example.com', 200, 0);

        // Assert
        expect($event->attempt)->toBe(0);
    });

    test('handles large number of total attempts', function (): void {
        // Arrange & Act
        $event = new FinalWebhookCallFailedEvent('wh_123', 'https://example.com', 1_000, new Exception());

        // Assert
        expect($event->totalAttempts)->toBe(1_000);
    });

    test('handles exception with very long message', function (): void {
        // Arrange
        $longMessage = str_repeat('Error. ', 1_000);
        $exception = new RuntimeException($longMessage);

        // Act
        $event = new WebhookCallFailedEvent('wh_123', 'https://example.com', 1, $exception);

        // Assert
        expect($event->exception->getMessage())->toBe($longMessage);
    });
});
