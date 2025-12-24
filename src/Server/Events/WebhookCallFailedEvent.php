<?php

declare(strict_types=1);

namespace Cline\Webhook\Server\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Fired when a webhook call attempt fails.
 */
final class WebhookCallFailedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $webhookId  Unique webhook identifier
     * @param  string  $url  Target URL
     * @param  int  $attempt  Attempt number
     * @param  Throwable  $exception  The exception that caused failure
     */
    public function __construct(
        public readonly string $webhookId,
        public readonly string $url,
        public readonly int $attempt,
        public readonly Throwable $exception,
    ) {}
}
