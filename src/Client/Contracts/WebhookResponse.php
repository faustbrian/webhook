<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Contracts;

use Cline\Webhook\Client\Models\WebhookCall;
use Illuminate\Http\Response;

/**
 * Defines the contract for generating HTTP responses to webhook requests.
 *
 * Implementers of this interface determine the HTTP response that should be
 * sent back to the webhook sender after processing. This allows customization
 * of response status codes, headers, and body content based on the webhook
 * call context and processing results.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface WebhookResponse
{
    /**
     * Generates the HTTP response for a processed webhook request.
     *
     * This method creates the response that will be sent back to the webhook
     * sender. The response should indicate successful receipt and may include
     * additional information such as processing status, identifiers, or metadata.
     *
     * @param WebhookCall $webhookCall The processed webhook call containing the
     *                                 payload, processing results, and metadata
     *
     * @return Response The HTTP response to send back to the webhook sender,
     *                  typically with a 200 status code for successful processing
     */
    public function response(WebhookCall $webhookCall): Response;
}
