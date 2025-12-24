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
 * Fired when a webhook call attempt fails.
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookCallFailedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param string    $webhookId Unique webhook identifier
     * @param string    $url       Target URL
     * @param int       $attempt   Attempt number
     * @param Throwable $exception The exception that caused failure
     */
    public function __construct(
        public readonly string $webhookId,
        public readonly string $url,
        public readonly int $attempt,
        public readonly Throwable $exception,
    ) {}
}
