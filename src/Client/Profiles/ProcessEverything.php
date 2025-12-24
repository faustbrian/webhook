<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Profiles;

use Cline\Webhook\Client\Contracts\WebhookProfile;
use Illuminate\Http\Request;

/**
 * Process all incoming webhooks without filtering.
 */
final class ProcessEverything implements WebhookProfile
{
    /**
     * {@inheritDoc}
     */
    public function shouldProcess(Request $request): bool
    {
        return true;
    }
}
