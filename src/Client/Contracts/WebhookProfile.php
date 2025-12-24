<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Contracts;

use Illuminate\Http\Request;

/**
 * Determines which webhooks should be processed.
 */
interface WebhookProfile
{
    /**
     * Determine if webhook should be processed.
     */
    public function shouldProcess(Request $request): bool;
}
