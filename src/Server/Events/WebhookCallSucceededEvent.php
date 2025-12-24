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

/**
 * Fired when a webhook call succeeds.
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class WebhookCallSucceededEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param string $webhookId  Unique webhook identifier
     * @param string $url        Target URL
     * @param int    $statusCode HTTP response status code
     * @param int    $attempt    Attempt number
     */
    public function __construct(
        public string $webhookId,
        public string $url,
        public int $statusCode,
        public int $attempt,
    ) {}
}
