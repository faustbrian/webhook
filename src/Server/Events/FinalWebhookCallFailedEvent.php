<?php

declare(strict_types=1);

namespace Cline\Webhook\Server\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Fired when all webhook retry attempts are exhausted.
 */
final class FinalWebhookCallFailedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $webhookId  Unique webhook identifier
     * @param  string  $url  Target URL
     * @param  int  $totalAttempts  Total number of attempts made
     * @param  Throwable  $lastException  The last exception encountered
     */
    public function __construct(
        public readonly string $webhookId,
        public readonly string $url,
        public readonly int $totalAttempts,
        public readonly Throwable $lastException,
    ) {}
}
