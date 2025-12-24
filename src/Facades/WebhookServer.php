<?php

declare(strict_types=1);

namespace Cline\Webhook\Facades;

use Cline\Webhook\Server\WebhookCall;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for server-side webhook dispatch.
 *
 * @method static WebhookCall create()
 *
 * @see WebhookCall
 */
final class WebhookServer extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return WebhookCall::class;
    }

    /**
     * Create a new webhook call instance.
     */
    public static function create(): WebhookCall
    {
        return WebhookCall::create();
    }
}
