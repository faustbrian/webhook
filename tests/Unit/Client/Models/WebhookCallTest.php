<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Client\Models;

use Carbon\Carbon;
use Cline\Webhook\Client\Models\Builders\WebhookCallBuilder;
use Cline\Webhook\Client\Models\WebhookCall;
use Cline\Webhook\Enums\WebhookStatus;
use Exception;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

use const PHP_INT_MAX;

use function array_merge;
use function beforeEach;
use function class_uses;
use function describe;
use function expect;
use function is_array;
use function json_encode;
use function Pest\Laravel\assertDatabaseHas;
use function str_repeat;
use function test;
use function uses;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Set up package configuration
    Config::set('webhook.primary_key_type', 'ulid');
    Config::set('webhook.client.configs.default.delete_after_days', 30);

    // Run migrations
    $this->loadMigrationsFrom(__DIR__.'/../../../../database/migrations');
});

/**
 * Helper function to create a webhook call with auto-generated ULID.
 */
function createWebhookCall(array $attributes = []): WebhookCall
{
    $defaults = [
        'config_name' => 'default',
        'webhook_id' => 'wh_'.Str::random(10),
        'timestamp' => 1_703_001_600,
        'payload' => json_encode([]),
        'status' => 'pending',
        'attempts' => 0,
    ];

    $merged = array_merge($defaults, $attributes);

    // Convert enum to string if present
    if (isset($merged['status']) && $merged['status'] instanceof WebhookStatus) {
        $merged['status'] = $merged['status']->value;
    }

    // Remove 'id' which is not fillable and will be auto-generated
    unset($merged['id']);

    // Use Eloquent create to properly test mass assignment protection
    $model = WebhookCall::query()->create($merged);

    // Set timestamps if provided (bypassing fillable since timestamps aren't fillable)
    if (isset($attributes['created_at'])) {
        $model->created_at = $attributes['created_at'];
    }

    if (isset($attributes['updated_at'])) {
        $model->updated_at = $attributes['updated_at'];
    }

    if (isset($attributes['created_at']) || isset($attributes['updated_at'])) {
        $model->saveQuietly();
    }

    return $model;
}

describe('Model Attributes', function (): void {
    test('uses mass prunable trait', function (): void {
        // Arrange & Act
        $traits = class_uses(WebhookCall::class);

        // Assert
        expect($traits)->toContain(MassPrunable::class);
    });

    test('uses correct eloquent builder', function (): void {
        // Arrange & Act
        $query = WebhookCall::query();

        // Assert
        expect($query)->toBeInstanceOf(WebhookCallBuilder::class);
    });

    test('has correct fillable attributes', function (): void {
        // Arrange
        $model = new WebhookCall();

        // Act
        $fillable = $model->getFillable();

        // Assert
        expect($fillable)->toBe([
            'config_name',
            'webhook_id',
            'timestamp',
            'payload',
            'headers',
            'status',
            'exception',
            'attempts',
            'processed_at',
        ]);
    });

    test('has correct casts', function (): void {
        // Arrange
        $model = new WebhookCall();

        // Act
        $casts = $model->getCasts();

        // Assert
        expect($casts)->toHaveKey('timestamp', 'integer')
            ->and($casts)->toHaveKey('payload', 'array')
            ->and($casts)->toHaveKey('headers', 'array')
            ->and($casts)->toHaveKey('status', WebhookStatus::class)
            ->and($casts)->toHaveKey('attempts', 'integer')
            ->and($casts)->toHaveKey('processed_at', 'datetime');
    });
});

describe('Mass Assignment', function (): void {
    test('allows mass assignment of fillable attributes', function (): void {
        // Arrange
        $data = [
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => ['event' => 'user.created'],
            'headers' => ['X-Custom' => 'value'],
            'status' => WebhookStatus::PENDING,
            'exception' => null,
            'attempts' => 0,
            'processed_at' => null,
        ];

        // Act
        $webhookCall = createWebhookCall($data);

        // Assert
        expect($webhookCall->config_name)->toBe('default')
            ->and($webhookCall->webhook_id)->toBe('wh_123456')
            ->and($webhookCall->timestamp)->toBe(1_703_001_600)
            ->and($webhookCall->payload)->toBe(['event' => 'user.created'])
            ->and($webhookCall->headers)->toBe(['X-Custom' => 'value'])
            ->and($webhookCall->status)->toBe(WebhookStatus::PENDING)
            ->and($webhookCall->exception)->toBeNull()
            ->and($webhookCall->attempts)->toBe(0)
            ->and($webhookCall->processed_at)->toBeNull();
    });

    test('protects non-fillable attributes from mass assignment', function (): void {
        // Arrange
        $data = [
            'id' => 'custom-id',
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => ['event' => 'user.created'],
        ];

        // Act
        $webhookCall = createWebhookCall($data);

        // Assert
        expect($webhookCall->id)->not->toBe('custom-id');
    });
});

describe('Casts', function (): void {
    test('casts payload to array', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => ['event' => 'user.created', 'data' => ['id' => 1]],
        ]);

        // Act

        // Assert
        expect($webhookCall->payload)->toBeArray()
            ->and($webhookCall->payload)->toBe(['event' => 'user.created', 'data' => ['id' => 1]]);
    });

    test('casts headers to array', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'headers' => ['X-Custom' => 'value', 'X-Signature' => 'abc123'],
        ]);

        // Act

        // Assert
        expect($webhookCall->headers)->toBeArray()
            ->and($webhookCall->headers)->toBe(['X-Custom' => 'value', 'X-Signature' => 'abc123']);
    });

    test('handles null headers', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'headers' => null,
        ]);

        // Act

        // Assert
        expect($webhookCall->headers)->toBeNull();
    });

    test('casts status to WebhookStatus enum', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSING,
        ]);

        // Act

        // Assert
        expect($webhookCall->status)->toBeInstanceOf(WebhookStatus::class)
            ->and($webhookCall->status)->toBe(WebhookStatus::PROCESSING);
    });

    test('casts timestamp to integer', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
        ]);

        // Act

        // Assert
        expect($webhookCall->timestamp)->toBeInt()
            ->and($webhookCall->timestamp)->toBe(1_703_001_600);
    });

    test('casts attempts to integer', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'attempts' => 3,
        ]);

        // Act

        // Assert
        expect($webhookCall->attempts)->toBeInt()
            ->and($webhookCall->attempts)->toBe(3);
    });

    test('casts processed_at to Carbon datetime', function (): void {
        // Arrange
        $now = Date::now();
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'processed_at' => $now,
        ]);

        // Act

        // Assert
        expect($webhookCall->processed_at)->toBeInstanceOf(Carbon::class)
            ->and($webhookCall->processed_at->toDateTimeString())->toBe($now->toDateTimeString());
    });

    test('handles null processed_at', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'processed_at' => null,
        ]);

        // Act

        // Assert
        expect($webhookCall->processed_at)->toBeNull();
    });
});

describe('markAsProcessing()', function (): void {
    test('updates status to processing', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PENDING,
            'attempts' => 0,
        ]);

        // Act
        $webhookCall->markAsProcessing();

        // Assert
        expect($webhookCall->status)->toBe(WebhookStatus::PROCESSING);
    });

    test('increments attempts counter', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PENDING,
            'attempts' => 0,
        ]);

        // Act
        $webhookCall->markAsProcessing();

        // Assert
        expect($webhookCall->attempts)->toBe(1);
    });

    test('increments attempts on subsequent calls', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PENDING,
            'attempts' => 0,
        ]);

        // Act
        $webhookCall->markAsProcessing();
        $webhookCall->markAsProcessing();
        $webhookCall->markAsProcessing();

        // Assert
        expect($webhookCall->attempts)->toBe(3);
    });

    test('persists changes to database', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_persist_processing',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PENDING,
            'attempts' => 0,
        ]);

        // Act
        $webhookCall->markAsProcessing();

        // Assert  - Verify by querying fresh from database
        $fresh = WebhookCall::query()->where('webhook_id', 'wh_persist_processing')->first();
        expect($fresh)->not->toBeNull()
            ->and($fresh->status)->toBe(WebhookStatus::PROCESSING)
            ->and($fresh->attempts)->toBe(1);
    });
});

describe('markAsProcessed()', function (): void {
    test('updates status to processed', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSING,
        ]);

        // Act
        $webhookCall->markAsProcessed();

        // Assert
        expect($webhookCall->status)->toBe(WebhookStatus::PROCESSED);
    });

    test('sets processed_at timestamp', function (): void {
        // Arrange
        Date::setTestNow('2024-01-01 12:00:00');
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSING,
        ]);

        // Act
        $webhookCall->markAsProcessed();

        // Assert
        expect($webhookCall->processed_at)->toBeInstanceOf(Carbon::class)
            ->and($webhookCall->processed_at->toDateTimeString())->toBe('2024-01-01 12:00:00');

        Date::setTestNow();
    });

    test('clears exception message', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::FAILED,
            'exception' => 'Previous error message',
        ]);

        // Act
        $webhookCall->markAsProcessed();

        // Assert
        expect($webhookCall->exception)->toBeNull();
    });

    test('persists changes to database', function (): void {
        // Arrange
        Date::setTestNow('2024-01-01 12:00:00');
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_persist_processed',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSING,
            'exception' => 'Old error',
        ]);

        // Act
        $webhookCall->markAsProcessed();

        // Assert - Verify by querying fresh from database
        $fresh = WebhookCall::query()->where('webhook_id', 'wh_persist_processed')->first();
        expect($fresh)->not->toBeNull()
            ->and($fresh->status)->toBe(WebhookStatus::PROCESSED)
            ->and($fresh->exception)->toBeNull()
            ->and($fresh->processed_at)->not->toBeNull();

        Date::setTestNow();
    });
});

describe('markAsFailed()', function (): void {
    test('updates status to failed', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSING,
        ]);

        // Act
        $webhookCall->markAsFailed();

        // Assert
        expect($webhookCall->status)->toBe(WebhookStatus::FAILED);
    });

    test('stores exception message when exception provided', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSING,
        ]);
        $exception = new RuntimeException('Processing failed');

        // Act
        $webhookCall->markAsFailed($exception);

        // Assert
        expect($webhookCall->exception)->toBe('Processing failed');
    });

    test('handles null exception', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSING,
        ]);

        // Act
        $webhookCall->markAsFailed(null);

        // Assert
        expect($webhookCall->exception)->toBeNull();
    });

    test('handles exception with empty message', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSING,
        ]);
        $exception = new Exception('');

        // Act
        $webhookCall->markAsFailed($exception);

        // Assert
        expect($webhookCall->exception)->toBe('');
    });

    test('overwrites existing exception message', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::FAILED,
            'exception' => 'Old error',
        ]);
        $exception = new RuntimeException('New error');

        // Act
        $webhookCall->markAsFailed($exception);

        // Assert
        expect($webhookCall->exception)->toBe('New error');
    });

    test('persists changes to database', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_persist_failed',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSING,
        ]);
        $exception = new RuntimeException('Database error');

        // Act
        $webhookCall->markAsFailed($exception);

        // Assert - Verify by querying fresh from database
        $fresh = WebhookCall::query()->where('webhook_id', 'wh_persist_failed')->first();
        expect($fresh)->not->toBeNull()
            ->and($fresh->status)->toBe(WebhookStatus::FAILED)
            ->and($fresh->exception)->toBe('Database error');
    });
});

describe('clearException()', function (): void {
    test('removes exception message', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'exception' => 'Error message',
        ]);

        // Act
        $webhookCall->clearException();

        // Assert
        expect($webhookCall->exception)->toBeNull();
    });

    test('handles already null exception', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'exception' => null,
        ]);

        // Act
        $webhookCall->clearException();

        // Assert
        expect($webhookCall->exception)->toBeNull();
    });

    test('persists changes to database', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_persist_clear',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'exception' => 'Error to clear',
        ]);

        // Act
        $webhookCall->clearException();

        // Assert - Verify by querying fresh from database
        $fresh = WebhookCall::query()->where('webhook_id', 'wh_persist_clear')->first();
        expect($fresh)->not->toBeNull()
            ->and($fresh->exception)->toBeNull();
    });
});

describe('prunable()', function (): void {
    test('returns query for records older than configured days', function (): void {
        // Arrange
        Config::set('webhook.client.configs.default.delete_after_days', 30);
        Date::setTestNow('2024-01-31 12:00:00');

        $oldWebhook = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_old',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'created_at' => Date::now()->subDays(31),
        ]);

        $recentWebhook = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_recent',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'created_at' => Date::now()->subDays(29),
        ]);

        // Act
        $prunable = $oldWebhook->prunable()->pluck('webhook_id')->toArray();

        // Assert
        expect($prunable)->toContain($oldWebhook->webhook_id)
            ->and($prunable)->not->toContain($recentWebhook->webhook_id);

        Date::setTestNow();
    });

    test('respects custom config name delete_after_days', function (): void {
        // Arrange
        Config::set('webhook.client.configs.custom.delete_after_days', 60);
        Date::setTestNow('2024-03-01 12:00:00');

        $webhook = createWebhookCall([
            'config_name' => 'custom',
            'webhook_id' => 'wh_custom',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'created_at' => Date::now()->subDays(61),
        ]);

        // Act
        $prunable = $webhook->prunable()->pluck('webhook_id')->toArray();

        // Assert
        expect($prunable)->toContain($webhook->webhook_id);

        Date::setTestNow();
    });

    test('uses default 30 days when config not set', function (): void {
        // Arrange
        Config::set('webhook.client.configs.nonexistent', null);
        Date::setTestNow('2024-01-31 12:00:00');

        $webhook = createWebhookCall([
            'config_name' => 'nonexistent',
            'webhook_id' => 'wh_default',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'created_at' => Date::now()->subDays(31),
        ]);

        // Act
        $prunable = $webhook->prunable()->pluck('webhook_id')->toArray();

        // Assert
        expect($prunable)->toContain($webhook->webhook_id);

        Date::setTestNow();
    });

    test('boundary condition at exact delete_after_days', function (): void {
        // Arrange
        Config::set('webhook.client.configs.default.delete_after_days', 30);
        Date::setTestNow('2024-01-31 12:00:00');

        $webhookExact = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_exact',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'created_at' => Date::now()->subDays(30),
        ]);

        // Act
        $prunable = $webhookExact->prunable()->pluck('webhook_id')->toArray();

        // Assert
        expect($prunable)->toContain($webhookExact->webhook_id);

        Date::setTestNow();
    });

    test('handles zero delete_after_days', function (): void {
        // Arrange
        Config::set('webhook.client.configs.default.delete_after_days', 0);
        Date::setTestNow('2024-01-31 12:00:00');

        $webhook = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_zero',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'created_at' => Date::now(),
        ]);

        // Act
        $prunable = $webhook->prunable()->pluck('webhook_id')->toArray();

        // Assert
        expect($prunable)->toContain($webhook->webhook_id);

        Date::setTestNow();
    });
});

describe('Custom Query Builder Scopes', function (): void {
    test('pending scope filters pending webhooks', function (): void {
        // Arrange
        $pending = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_pending',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PENDING,
        ]);

        $processing = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_processing',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSING,
        ]);

        // Act
        $results = WebhookCall::query()->pending()->pluck('webhook_id')->toArray();

        // Assert
        expect($results)->toContain($pending->webhook_id)
            ->and($results)->not->toContain($processing->webhook_id);
    });

    test('processing scope filters processing webhooks', function (): void {
        // Arrange
        $pending = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_pending',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PENDING,
        ]);

        $processing = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_processing',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSING,
        ]);

        // Act
        $results = WebhookCall::query()->processing()->pluck('webhook_id')->toArray();

        // Assert
        expect($results)->toContain($processing->webhook_id)
            ->and($results)->not->toContain($pending->webhook_id);
    });

    test('processed scope filters processed webhooks', function (): void {
        // Arrange
        $processed = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_processed',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSED,
        ]);

        $failed = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_failed',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::FAILED,
        ]);

        // Act
        $results = WebhookCall::query()->processed()->pluck('webhook_id')->toArray();

        // Assert
        expect($results)->toContain($processed->webhook_id)
            ->and($results)->not->toContain($failed->webhook_id);
    });

    test('failed scope filters failed webhooks', function (): void {
        // Arrange
        $processed = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_processed',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSED,
        ]);

        $failed = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_failed',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::FAILED,
        ]);

        // Act
        $results = WebhookCall::query()->failed()->pluck('webhook_id')->toArray();

        // Assert
        expect($results)->toContain($failed->webhook_id)
            ->and($results)->not->toContain($processed->webhook_id);
    });

    test('forConfig scope filters by config name', function (): void {
        // Arrange
        $default = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_default',
            'timestamp' => 1_703_001_600,
            'payload' => [],
        ]);

        $custom = createWebhookCall([
            'config_name' => 'custom',
            'webhook_id' => 'wh_custom',
            'timestamp' => 1_703_001_600,
            'payload' => [],
        ]);

        // Act
        $results = WebhookCall::query()->forConfig('default')->pluck('webhook_id')->toArray();

        // Assert
        expect($results)->toContain($default->webhook_id)
            ->and($results)->not->toContain($custom->webhook_id);
    });

    test('byWebhookId scope filters by webhook id', function (): void {
        // Arrange
        $webhook1 = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_123',
            'timestamp' => 1_703_001_600,
            'payload' => [],
        ]);

        $webhook2 = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_456',
            'timestamp' => 1_703_001_600,
            'payload' => [],
        ]);

        // Act
        $results = WebhookCall::query()->byWebhookId('wh_123')->pluck('webhook_id')->toArray();

        // Assert
        expect($results)->toContain($webhook1->webhook_id)
            ->and($results)->not->toContain($webhook2->webhook_id);
    });

    test('scopes can be chained', function (): void {
        // Arrange
        $target = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_target',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::FAILED,
        ]);

        $otherConfig = createWebhookCall([
            'config_name' => 'custom',
            'webhook_id' => 'wh_other_config',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::FAILED,
        ]);

        $otherStatus = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_other_status',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'status' => WebhookStatus::PROCESSED,
        ]);

        // Act
        $results = WebhookCall::query()
            ->forConfig('default')
            ->failed()
            ->pluck('webhook_id')
            ->toArray();

        // Assert
        expect($results)->toContain($target->webhook_id)
            ->and($results)->not->toContain($otherConfig->webhook_id)
            ->and($results)->not->toContain($otherStatus->webhook_id);
    });
});

describe('Edge Cases', function (): void {
    test('handles very long exception messages', function (): void {
        // Arrange
        $longMessage = str_repeat('Error message. ', 1_000);
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_long',
            'timestamp' => 1_703_001_600,
            'payload' => [],
        ]);
        $exception = new RuntimeException($longMessage);

        // Act
        $webhookCall->markAsFailed($exception);

        // Assert
        expect($webhookCall->exception)->toBe($longMessage);
    });

    test('handles special characters in exception message', function (): void {
        // Arrange
        $specialMessage = "Error with 'quotes' and \"double quotes\" and \n newlines \t tabs";
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_special',
            'timestamp' => 1_703_001_600,
            'payload' => [],
        ]);
        $exception = new RuntimeException($specialMessage);

        // Act
        $webhookCall->markAsFailed($exception);

        // Assert
        expect($webhookCall->exception)->toBe($specialMessage);
    });

    test('handles unicode in payload', function (): void {
        // Arrange
        $unicodePayload = [
            'message' => 'Hello 世界 🌍',
            'emoji' => '😀🎉',
        ];

        // Act
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_unicode',
            'timestamp' => 1_703_001_600,
            'payload' => $unicodePayload,
        ]);

        // Assert
        expect($webhookCall->payload)->toBe($unicodePayload);
    });

    test('handles empty payload array', function (): void {
        // Arrange & Act
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_empty',
            'timestamp' => 1_703_001_600,
            'payload' => [],
        ]);

        // Assert
        expect($webhookCall->payload)->toBe([]);
    });

    test('handles deeply nested payload', function (): void {
        // Arrange
        $deepPayload = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'value' => 'deep',
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_deep',
            'timestamp' => 1_703_001_600,
            'payload' => $deepPayload,
        ]);

        // Assert
        expect($webhookCall->payload)->toBe($deepPayload);
    });

    test('handles maximum integer timestamp', function (): void {
        // Arrange & Act
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_max_timestamp',
            'timestamp' => PHP_INT_MAX,
            'payload' => [],
        ]);

        // Assert
        expect($webhookCall->timestamp)->toBe(PHP_INT_MAX);
    });

    test('handles zero attempts', function (): void {
        // Arrange & Act
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_zero_attempts',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'attempts' => 0,
        ]);

        // Assert
        expect($webhookCall->attempts)->toBe(0);
    });

    test('handles large number of attempts', function (): void {
        // Arrange
        $webhookCall = createWebhookCall([
            'config_name' => 'default',
            'webhook_id' => 'wh_many_attempts',
            'timestamp' => 1_703_001_600,
            'payload' => [],
            'attempts' => 0,
        ]);

        // Act
        for ($i = 0; $i < 100; ++$i) {
            $webhookCall->markAsProcessing();
        }

        // Assert
        expect($webhookCall->attempts)->toBe(100);
    });
});
