<?php

declare(strict_types=1);

namespace Cline\Webhook\Server\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired before dispatching a webhook call.
 */
final class DispatchingWebhookCallEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $webhookId  Unique webhook identifier
     * @param  string  $url  Target URL
     * @param  array<string, mixed>  $payload  Webhook payload
     * @param  array<string, string>  $headers  HTTP headers
     */
    public function __construct(
        public readonly string $webhookId,
        public readonly string $url,
        public readonly array $payload,
        public readonly array $headers,
    ) {}
}
