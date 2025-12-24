<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Events;

use Cline\Webhook\Client\Models\WebhookCall;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a webhook is successfully processed.
 */
final class WebhookProcessedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly WebhookCall $webhookCall,
    ) {}
}
