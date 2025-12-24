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
 * Facade for client-side webhook processing.
 *
 * @method static Response process(Request $request, string $configName = 'default')
 *
 * @author Brian Faust <brian@cline.sh>
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
