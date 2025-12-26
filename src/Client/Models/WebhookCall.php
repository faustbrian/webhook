<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Models;

use Carbon\Carbon;
use Cline\VariableKeys\Database\Concerns\HasVariablePrimaryKey;
use Cline\Webhook\Client\Models\Builders\WebhookCallBuilder;
use Cline\Webhook\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Throwable;

use function sprintf;

/**
 * Eloquent model representing a webhook call received from an external source.
 *
 * Stores the complete webhook payload, headers, and processing metadata for
 * webhook calls received from configured external services. Tracks processing
 * status through pending -> processing -> processed/failed lifecycle and
 * supports automatic pruning of old records based on configuration settings.
 *
 * @property int                        $attempts     Number of processing attempts made (increments on retry)
 * @property string                     $config_name  Configuration name from webhook.client.configs used for this webhook
 * @property Carbon                     $created_at   Timestamp when webhook was received
 * @property null|string                $exception    Exception message if processing failed, null otherwise
 * @property null|array<string, string> $headers      Filtered HTTP headers based on store_headers configuration
 * @property string                     $id           Primary key (ULID, UUID, or auto-increment based on config)
 * @property array<string, mixed>       $payload      Decoded JSON payload from webhook request body
 * @property null|Carbon                $processed_at Timestamp when webhook was successfully processed, null if pending/failed
 * @property WebhookStatus              $status       Current processing status (PENDING, PROCESSING, PROCESSED, FAILED)
 * @property int                        $timestamp    Unix timestamp from webhook-timestamp header for replay attack prevention
 * @property Carbon                     $updated_at   Timestamp of last status update
 * @property string                     $webhook_id   External webhook identifier from webhook-id header
 *
 * @phpstan-type TFactory Factory<static>
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[UseEloquentBuilder(WebhookCallBuilder::class)]
final class WebhookCall extends Model
{
    /** @use HasFactory<TFactory> */
    use HasFactory;
    use HasVariablePrimaryKey;
    use MassPrunable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp' => 'integer',
        'payload' => 'array',
        'headers' => 'array',
        'status' => WebhookStatus::class,
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the prunable model query for automatic cleanup via Laravel's model pruning.
     *
     * Returns a query for webhook calls older than the configured delete_after_days
     * setting. Used by Laravel's model:prune Artisan command to automatically
     * clean up old webhook records and prevent unbounded database growth.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        /** @var int $days */
        $days = Config::get(sprintf('webhook.client.configs.%s.delete_after_days', $this->config_name), 30);

        return self::query()->where('created_at', '<=', Date::now()->subDays($days));
    }

    /**
     * Mark webhook as currently being processed.
     *
     * Updates status to PROCESSING and increments the attempts counter.
     * Called when a processing job begins execution.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => WebhookStatus::PROCESSING,
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Mark webhook as successfully processed.
     *
     * Updates status to PROCESSED, records the processing timestamp, and
     * clears any previous exception message. Called after successful completion
     * of webhook processing logic.
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'status' => WebhookStatus::PROCESSED,
            'processed_at' => Date::now(),
            'exception' => null,
        ]);
    }

    /**
     * Mark webhook as failed with optional exception details.
     *
     * Updates status to FAILED and stores the exception message for debugging.
     * Called when webhook processing throws an exception or encounters an error.
     *
     * @param null|Throwable $exception The exception that caused the failure, if available
     */
    public function markAsFailed(?Throwable $exception = null): void
    {
        $this->update([
            'status' => WebhookStatus::FAILED,
            'exception' => $exception?->getMessage(),
        ]);
    }

    /**
     * Clear the stored exception message.
     *
     * Useful when retrying a previously failed webhook to reset error state
     * before attempting reprocessing.
     */
    public function clearException(): void
    {
        $this->update(['exception' => null]);
    }
}
