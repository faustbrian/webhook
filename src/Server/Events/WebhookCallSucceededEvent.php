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
 * Fired when a webhook delivery succeeds with a 2xx HTTP response.
 *
 * This event confirms successful webhook delivery after receiving
 * a successful HTTP response (status code 200-299) from the target
 * endpoint. Listeners can use this for success tracking, metrics,
 * or triggering follow-up actions.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class WebhookCallSucceededEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new webhook success event.
     *
     * @param string $webhookId  Unique identifier for the webhook that was successfully delivered
     * @param string $url        Target URL that successfully received and acknowledged the webhook
     * @param int    $statusCode HTTP response status code (200-299) returned by the receiving endpoint
     * @param int    $attempt    Attempt number on which success occurred (1 for immediate success, higher if retries were needed)
     */
    public function __construct(
        public string $webhookId,
        public string $url,
        public int $statusCode,
        public int $attempt,
    ) {}
}
