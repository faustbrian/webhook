<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Http\Controllers;

use Cline\Webhook\Client\WebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Handles incoming webhook requests.
 *
 * This controller serves as the entry point for all incoming webhook HTTP
 * requests. It delegates to the WebhookProcessor for validation, storage,
 * and processing of webhook payloads using the specified configuration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookController extends Controller
{
    /**
     * Handles an incoming webhook request.
     *
     * This method receives the webhook HTTP request and delegates processing
     * to the WebhookProcessor. The processor performs signature validation,
     * stores the webhook call, and invokes the configured handler.
     *
     * @param Request $request    The incoming HTTP request containing the webhook
     *                            payload, signature headers, and metadata from
     *                            the webhook sender
     * @param string  $configName The webhook configuration name to use for processing,
     *                            defaults to 'default'. This determines which secret,
     *                            validator, and processor are used for this request
     *
     * @return Response The HTTP response to send back to the webhook sender,
     *                  indicating successful receipt and processing status
     */
    public function __invoke(Request $request, string $configName = 'default'): Response
    {
        $processor = new WebhookProcessor($configName);

        return $processor->process($request);
    }
}
