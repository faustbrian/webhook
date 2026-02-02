<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Facades;

use Cline\Webhook\Client\WebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for receiving and validating incoming webhook requests.
 *
 * Provides a convenient static interface for processing webhooks sent to your
 * application. Handles signature verification, timestamp validation, duplicate
 * detection, and dispatches validated webhooks to registered handlers. Used
 * in route controllers to process incoming webhook HTTP requests.
 *
 * ```php
 * Route::post('/webhooks/stripe', function (Request $request) {
 *     return WebhookClient::process($request, 'stripe');
 * });
 * ```
 *
 * @method static Response process(Request $request, string $configName = 'default') Process incoming webhook request with validation and handler dispatch
 *
 * @author Brian Faust <brian@cline.sh>
 * @see WebhookProcessor
 */
final class WebhookClient extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * Returns the service container binding key that this facade provides
     * access to. Laravel resolves this accessor to retrieve the underlying
     * WebhookProcessor instance from the container.
     *
     * @return string The fully qualified class name used for container resolution
     */
    protected static function getFacadeAccessor(): string
    {
        return WebhookProcessor::class;
    }
}
