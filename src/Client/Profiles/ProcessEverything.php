<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Profiles;

use Cline\Webhook\Client\Contracts\WebhookProfile;
use Illuminate\Http\Request;

/**
 * Default webhook profile that processes all incoming webhooks without filtering.
 *
 * This permissive profile accepts all webhook requests that pass signature
 * validation. Use this when you want to process every webhook from a source,
 * or implement custom filtering logic within your webhook processor instead.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ProcessEverything implements WebhookProfile
{
    /**
     * Determine if the webhook request should be processed.
     *
     * Always returns true, allowing all validated webhooks to proceed to
     * processing and storage. Override this class or implement WebhookProfile
     * with custom logic to filter specific webhook types or events.
     *
     * @param  Request $request The incoming webhook HTTP request
     * @return bool    Always true to process all webhooks
     */
    public function shouldProcess(Request $request): bool
    {
        return true;
    }
}
