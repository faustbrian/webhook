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
 * Fired when all webhook retry attempts are exhausted.
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class FinalWebhookCallFailedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param string    $webhookId     Unique webhook identifier
     * @param string    $url           Target URL
     * @param int       $totalAttempts Total number of attempts made
     * @param Throwable $lastException The last exception encountered
     */
    public function __construct(
        public string $webhookId,
        public string $url,
        public int $totalAttempts,
        public Throwable $lastException,
    ) {}
}
