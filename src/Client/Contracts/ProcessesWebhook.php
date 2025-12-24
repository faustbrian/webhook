<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Contracts;

use Cline\Webhook\Client\Models\WebhookCall;

/**
 * Defines the contract for processing incoming webhook calls.
 *
 * Implementers of this interface contain the business logic for handling
 * specific webhook events after they have been validated and stored. This
 * is where webhook-specific processing occurs, such as updating database
 * records, triggering notifications, or integrating with external services.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ProcessesWebhook
{
    /**
     * Processes a validated webhook call.
     *
     * This method is invoked after signature verification and storage of the
     * webhook payload. Implementations should contain the specific business
     * logic needed to handle the webhook event type.
     *
     * @param WebhookCall $webhookCall The stored webhook call containing the payload,
     *                                 headers, and metadata from the incoming request
     */
    public function process(WebhookCall $webhookCall): void;
}
