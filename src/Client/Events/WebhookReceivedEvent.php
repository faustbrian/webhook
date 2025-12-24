<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Events;

use Cline\Webhook\Client\Models\WebhookCall;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a valid webhook is received and stored.
 */
final class WebhookReceivedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly WebhookCall $webhookCall,
        public readonly string $configName,
    ) {}
}
