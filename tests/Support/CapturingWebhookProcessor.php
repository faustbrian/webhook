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

/**
 * Test webhook processor that captures the webhook call for inspection.
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class CapturingWebhookProcessor implements ProcessesWebhook
{
    public static ?WebhookCall $captured = null;

    public static function reset(): void
    {
        self::$captured = null;
    }

    public function process(WebhookCall $webhookCall): void
    {
        self::$captured = $webhookCall;
    }
}
