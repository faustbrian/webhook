<?php

declare(strict_types=1);

namespace Cline\Webhook\Facades;

use Cline\Webhook\Client\WebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for client-side webhook processing.
 *
 * @method static Response process(Request $request, string $configName = 'default')
 *
 * @see WebhookProcessor
 */
final class WebhookClient extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return WebhookProcessor::class;
    }
}
