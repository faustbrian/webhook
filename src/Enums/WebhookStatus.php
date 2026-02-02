<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Enums;

use function in_array;

/**
 * Webhook processing lifecycle status tracking.
 *
 * Represents the current state of a webhook call within the processing pipeline,
 * from initial receipt through final completion or failure. Used for monitoring,
 * retry logic, and determining which webhooks are eligible for reprocessing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum WebhookStatus: string
{
    /**
     * Initial state after webhook receipt, awaiting processing.
     *
     * Webhook has been received and validated but has not yet been
     * dispatched to a handler. This is the default state for new webhooks
     * waiting in the queue for processing.
     */
    case PENDING = 'pending';

    /**
     * Webhook is currently being executed by a handler.
     *
     * Indicates active processing to prevent duplicate execution and track
     * in-flight webhooks. Transitions to either PROCESSED or FAILED upon
     * completion of the handler execution.
     */
    case PROCESSING = 'processing';

    /**
     * Webhook has been successfully processed and completed.
     *
     * Terminal state indicating the webhook handler executed without errors
     * and the webhook lifecycle is complete. Processed webhooks are not
     * eligible for retry or reprocessing.
     */
    case PROCESSED = 'processed';

    /**
     * Webhook processing encountered an error and failed.
     *
     * Terminal state indicating the handler threw an exception or returned
     * an error status. Failed webhooks may be eligible for retry depending
     * on retry configuration and failure reasons.
     */
    case FAILED = 'failed';

    /**
     * Determine if the webhook has reached a final, unchangeable state.
     *
     * Terminal states indicate the webhook processing lifecycle is complete
     * and no further state transitions will occur. Useful for cleanup operations,
     * reporting, and determining if a webhook record can be archived.
     *
     * @return bool True if the webhook is in PROCESSED or FAILED state,
     *              indicating the processing lifecycle has concluded
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::PROCESSED, self::FAILED], true);
    }

    /**
     * Determine if the webhook is eligible for processing or reprocessing.
     *
     * Identifies webhooks that can be dispatched to handlers, either for
     * initial processing or retry attempts. Processing webhooks are excluded
     * to prevent duplicate concurrent execution.
     *
     * @return bool True if the webhook is in PENDING or FAILED state,
     *              indicating it can be queued for (re)processing
     */
    public function canProcess(): bool
    {
        return in_array($this, [self::PENDING, self::FAILED], true);
    }
}
