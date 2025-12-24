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
 * @author Brian Faust <brian@cline.sh>
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
