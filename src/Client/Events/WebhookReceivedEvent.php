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
 * Fired when a valid webhook is received and stored.
 *
 * This event is dispatched after a webhook request has passed signature
 * validation and has been successfully stored in the database, but before
 * the processing handler is invoked. Listeners can use this event for
 * real-time monitoring, analytics, or triggering parallel workflows.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class WebhookReceivedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new webhook received event.
     *
     * @param WebhookCall $webhookCall The validated and stored webhook call,
     *                                 containing the payload, headers, and metadata
     *                                 from the incoming request ready for processing
     * @param string      $configName  The webhook configuration name that was used
     *                                 to receive and validate this webhook, identifying
     *                                 the endpoint and processing rules applied
     */
    public function __construct(
        public WebhookCall $webhookCall,
        public string $configName,
    ) {}
}
