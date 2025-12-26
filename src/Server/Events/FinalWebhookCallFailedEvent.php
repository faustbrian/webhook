<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Fired when all webhook retry attempts are exhausted without success.
 *
 * This event indicates permanent webhook delivery failure after all
 * configured retry attempts have been made. Listeners can implement
 * dead letter queue handling, alerting, or alternative delivery mechanisms.
 * This is the final notification before the job is marked as failed.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class FinalWebhookCallFailedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new final webhook failure event.
     *
     * @param string    $webhookId     Unique identifier for the webhook that failed permanently
     * @param string    $url           Target URL that could not be reached after all retry attempts
     * @param int       $totalAttempts Total number of delivery attempts made including the initial attempt and all retries
     * @param Throwable $lastException The exception from the final failed attempt, contains the most recent error details
     */
    public function __construct(
        public string $webhookId,
        public string $url,
        public int $totalAttempts,
        public Throwable $lastException,
    ) {}
}
