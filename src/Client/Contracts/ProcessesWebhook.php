<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Contracts;

use Cline\Webhook\Client\Models\WebhookCall;

/**
 * Defines webhook processing logic.
 */
interface ProcessesWebhook
{
    /**
     * Process the webhook call.
     */
    public function process(WebhookCall $webhookCall): void;
}
