<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Contracts;

use Cline\Webhook\Client\Models\WebhookCall;
use Illuminate\Http\Response;

/**
 * Defines the HTTP response for webhook requests.
 */
interface WebhookResponse
{
    /**
     * Generate response for webhook request.
     */
    public function response(WebhookCall $webhookCall): Response;
}
