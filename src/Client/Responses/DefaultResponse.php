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
 * Default webhook response handler.
 * @author Brian Faust <brian@cline.sh>
 */
final class DefaultResponse implements WebhookResponse
{
    /**
     * {@inheritDoc}
     */
    public function response(WebhookCall $webhookCall): Response
    {
        return new Response('Webhook received', \Symfony\Component\HttpFoundation\Response::HTTP_OK);
    }
}
