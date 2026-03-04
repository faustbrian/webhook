<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Contracts;

use Illuminate\Http\Request;

/**
 * Defines the contract for filtering which webhooks should be processed.
 *
 * Implementers of this interface provide filtering logic to determine whether
 * a webhook request should be processed or ignored. This allows for selective
 * handling based on event types, payload content, headers, or other criteria,
 * enabling efficient webhook processing and resource optimization.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface WebhookProfile
{
    /**
     * Determines if the incoming webhook request should be processed.
     *
     * This method evaluates the webhook request against filtering criteria
     * to decide whether it warrants processing. Common use cases include
     * filtering by event type, checking payload conditions, or enforcing
     * rate limiting rules.
     *
     * @param Request $request The incoming HTTP request containing the webhook
     *                         payload, headers, and metadata to evaluate
     *
     * @return bool Returns true if the webhook should be processed, false to skip it
     */
    public function shouldProcess(Request $request): bool;
}
