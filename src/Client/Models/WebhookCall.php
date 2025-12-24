<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Models;

use Carbon\Carbon;
use Cline\Webhook\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Webhook call received from external source.
 *
 * @property string $id
 * @property string $config_name
 * @property string $webhook_id
 * @property int $timestamp
 * @property array<string, mixed> $payload
 * @property array<string, string>|null $headers
 * @property WebhookStatus $status
 * @property string|null $exception
 * @property int $attempts
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class WebhookCall extends Model
{
    use HasFactory;
    use MassPrunable;

    protected $fillable = [
        'config_name',
        'webhook_id',
        'timestamp',
        'payload',
        'headers',
        'status',
        'exception',
        'attempts',
        'processed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'timestamp' => 'integer',
        'payload' => 'array',
        'headers' => 'array',
        'status' => WebhookStatus::class,
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the prunable model query for automatic cleanup.
     */
    public function prunable(): Builder
    {
        $days = Config::get("webhook.client.configs.{$this->config_name}.delete_after_days", 30);

        return static::where('created_at', '<=', Carbon::now()->subDays($days));
    }

    /**
     * Mark webhook as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => WebhookStatus::PROCESSING,
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Mark webhook as processed successfully.
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'status' => WebhookStatus::PROCESSED,
            'processed_at' => Carbon::now(),
            'exception' => null,
        ]);
    }

    /**
     * Mark webhook as failed.
     */
    public function markAsFailed(?\Throwable $exception = null): void
    {
        $this->update([
            'status' => WebhookStatus::FAILED,
            'exception' => $exception?->getMessage(),
        ]);
    }

    /**
     * Clear exception message.
     */
    public function clearException(): void
    {
        $this->update(['exception' => null]);
    }

    /**
     * Scope to pending webhooks.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', WebhookStatus::PENDING);
    }

    /**
     * Scope to processing webhooks.
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', WebhookStatus::PROCESSING);
    }

    /**
     * Scope to processed webhooks.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', WebhookStatus::PROCESSED);
    }

    /**
     * Scope to failed webhooks.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', WebhookStatus::FAILED);
    }

    /**
     * Scope to specific config.
     */
    public function scopeForConfig(Builder $query, string $configName): Builder
    {
        return $query->where('config_name', $configName);
    }

    /**
     * Scope to specific webhook ID.
     */
    public function scopeByWebhookId(Builder $query, string $webhookId): Builder
    {
        return $query->where('webhook_id', $webhookId);
    }
}
