<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Events;

use Cline\Webhook\Client\Models\WebhookCall;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a webhook is successfully processed.
 *
 * This event is dispatched after a webhook has been validated, stored, and
 * processed by its handler. Listeners can use this event to trigger follow-up
 * actions, update metrics, send notifications, or perform cleanup operations
 * after successful webhook processing.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class WebhookProcessedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new webhook processed event.
     *
     * @param WebhookCall $webhookCall The successfully processed webhook call,
     *                                 containing the complete payload, headers,
     *                                 processing results, and timestamp information
     *                                 for audit trails and downstream integrations
     */
    public function __construct(
        public WebhookCall $webhookCall,
    ) {}
}
