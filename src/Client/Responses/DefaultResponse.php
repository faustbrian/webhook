<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Responses;

use Cline\Webhook\Client\Contracts\WebhookResponse;
use Cline\Webhook\Client\Models\WebhookCall;
use Illuminate\Http\Response;

/**
 * Default webhook response handler.
 */
final class DefaultResponse implements WebhookResponse
{
    /**
     * {@inheritDoc}
     */
    public function response(WebhookCall $webhookCall): Response
    {
        return new Response('Webhook received', 200);
    }
}
