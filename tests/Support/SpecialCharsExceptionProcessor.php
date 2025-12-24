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
use RuntimeException;

/**
 * Test webhook processor that throws exception with special characters.
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class SpecialCharsExceptionProcessor implements ProcessesWebhook
{
    public function process(WebhookCall $webhookCall): void
    {
        throw new RuntimeException('Error with special chars: <>&"\'');
    }
}
