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
 * Fired when a single webhook delivery attempt fails.
 *
 * This event is dispatched for each failed attempt, including both the
 * initial delivery and subsequent retries. It allows listeners to track
 * individual failures, implement custom logging, or trigger alerts for
 * problematic webhooks. More attempts may follow unless retries are exhausted.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class WebhookCallFailedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new webhook call failure event.
     *
     * @param string    $webhookId Unique identifier for the webhook that failed this attempt
     * @param string    $url       Target URL that could not be reached or returned an error
     * @param int       $attempt   Current attempt number (1-indexed) indicating which retry this represents
     * @param Throwable $exception The exception that caused this attempt to fail, may include HTTP errors or network issues
     */
    public function __construct(
        public string $webhookId,
        public string $url,
        public int $attempt,
        public Throwable $exception,
    ) {}
}
