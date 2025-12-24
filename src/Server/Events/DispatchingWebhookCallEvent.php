<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired before dispatching a webhook call.
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class DispatchingWebhookCallEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param string                $webhookId Unique webhook identifier
     * @param string                $url       Target URL
     * @param array<string, mixed>  $payload   Webhook payload
     * @param array<string, string> $headers   HTTP headers
     */
    public function __construct(
        public string $webhookId,
        public string $url,
        public array $payload,
        public array $headers,
    ) {}
}
