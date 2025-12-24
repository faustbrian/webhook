<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a webhook signature verification fails.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidWebhookSignatureEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Request $request,
        public readonly string $configName,
    ) {}
}
