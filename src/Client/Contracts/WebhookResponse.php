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
 * Defines the HTTP response for webhook requests.
 * @author Brian Faust <brian@cline.sh>
 */
interface WebhookResponse
{
    /**
     * Generate response for webhook request.
     */
    public function response(WebhookCall $webhookCall): Response;
}
