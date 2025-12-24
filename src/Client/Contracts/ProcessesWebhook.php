<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Contracts;

use Cline\Webhook\Client\Models\WebhookCall;

/**
 * Defines webhook processing logic.
 * @author Brian Faust <brian@cline.sh>
 */
interface ProcessesWebhook
{
    /**
     * Process the webhook call.
     */
    public function process(WebhookCall $webhookCall): void;
}
