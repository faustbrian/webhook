<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Models;

use Carbon\Carbon;
use Cline\Webhook\Client\Models\Builders\WebhookCallBuilder;
use Cline\Webhook\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Webhook call received from external source.
 *
 * @property int                        $attempts
 * @property string                     $config_name
 * @property Carbon                     $created_at
 * @property null|string                $exception
 * @property null|array<string, string> $headers
 * @property string                     $id
 * @property array<string, mixed>       $payload
 * @property null|Carbon                $processed_at
 * @property WebhookStatus              $status
 * @property int                        $timestamp
 * @property Carbon                     $updated_at
 * @property string                     $webhook_id
 * @author Brian Faust <brian@cline.sh>
 */
#[UseEloquentBuilder(WebhookCallBuilder::class)]
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

        return self::where('created_at', '<=', Carbon::now()->subDays($days));
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
    public function markAsFailed(?Throwable $exception = null): void
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
}
