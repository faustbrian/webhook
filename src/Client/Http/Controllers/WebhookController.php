<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Http\Controllers;

use Cline\Webhook\Client\WebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Handles incoming webhook requests.
 */
final class WebhookController extends Controller
{
    /**
     * Handle webhook request.
     */
    public function __invoke(Request $request, string $configName = 'default'): Response
    {
        $processor = new WebhookProcessor($configName);

        return $processor->process($request);
    }
}
