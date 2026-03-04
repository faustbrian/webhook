<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support;

use Cline\Webhook\Client\Contracts\ProcessesWebhook;
use Cline\Webhook\Client\Models\WebhookCall;
use Tests\Exceptions\TestProcessingException;

/**
 * Test webhook processor that always throws an exception.
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class FailingWebhookProcessor implements ProcessesWebhook
{
    public function process(WebhookCall $webhookCall): void
    {
        throw TestProcessingException::simulatedFailure();
    }
}
