<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Events;

use Cline\Webhook\Client\Models\WebhookCall;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a valid webhook is received and stored.
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class WebhookReceivedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public WebhookCall $webhookCall,
        public string $configName,
    ) {}
}
