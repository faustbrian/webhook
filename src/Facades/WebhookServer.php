<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Facades;

use Cline\Webhook\Server\WebhookCall;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for server-side webhook dispatch.
 *
 * @method static WebhookCall create()
 *
 * @author Brian Faust <brian@cline.sh>
 * @see WebhookCall
 */
final class WebhookServer extends Facade
{
    /**
     * Create a new webhook call instance.
     */
    public static function create(): WebhookCall
    {
        return WebhookCall::create();
    }

    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return WebhookCall::class;
    }
}
