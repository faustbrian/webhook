<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a webhook signature verification fails.
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
