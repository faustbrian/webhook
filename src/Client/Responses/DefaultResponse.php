<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Responses;

use Cline\Webhook\Client\Contracts\WebhookResponse;
use Cline\Webhook\Client\Models\WebhookCall;
use Illuminate\Http\Response;

/**
 * Default webhook response handler that returns a simple success message.
 *
 * Provides a standard 200 OK response to acknowledge webhook receipt. This is
 * the default response handler used when no custom response logic is configured.
 * Override or implement WebhookResponse to provide custom response formatting,
 * status codes, or additional headers.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DefaultResponse implements WebhookResponse
{
    /**
     * Generate HTTP response for a received webhook.
     *
     * Returns a simple 200 OK response with "Webhook received" message to
     * acknowledge successful receipt and queuing of the webhook. The webhook
     * sender receives this response immediately, before processing begins.
     *
     * @param  WebhookCall $webhookCall The stored webhook call record
     * @return Response    HTTP 200 response acknowledging webhook receipt
     */
    public function response(WebhookCall $webhookCall): Response
    {
        return new Response('Webhook received', \Symfony\Component\HttpFoundation\Response::HTTP_OK);
    }
}
