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
 * Test webhook processor that always succeeds.
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class SuccessfulWebhookProcessor implements ProcessesWebhook
{
    public static bool $processed = false;

    public static function reset(): void
    {
        self::$processed = false;
    }

    public function process(WebhookCall $webhookCall): void
    {
        self::$processed = true;
    }
}
