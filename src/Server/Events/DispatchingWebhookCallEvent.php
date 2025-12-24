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
 * Fired immediately before dispatching a webhook HTTP request.
 *
 * This event allows listeners to observe or log webhook dispatch attempts
 * before the HTTP request is sent. It provides access to the complete
 * webhook configuration including headers and payload.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class DispatchingWebhookCallEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new webhook dispatching event.
     *
     * @param string                $webhookId Unique identifier for this webhook instance, used for tracking and signatures
     * @param string                $url       Target URL where the webhook will be sent
     * @param array<string, mixed>  $payload   Webhook payload data that will be JSON-encoded and sent in the request body
     * @param array<string, string> $headers   Complete set of HTTP headers including authentication, content-type, and signature headers
     */
    public function __construct(
        public string $webhookId,
        public string $url,
        public array $payload,
        public array $headers,
    ) {}
}
