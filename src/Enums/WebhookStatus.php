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
 * Webhook processing status.
 * @author Brian Faust <brian@cline.sh>
 */
enum WebhookStatus: string
{
    /**
     * Webhook pending processing.
     */
    case PENDING = 'pending';

    /**
     * Webhook currently processing.
     */
    case PROCESSING = 'processing';

    /**
     * Webhook processed successfully.
     */
    case PROCESSED = 'processed';

    /**
     * Webhook processing failed.
     */
    case FAILED = 'failed';

    /**
     * Check if webhook is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::PROCESSED, self::FAILED], true);
    }

    /**
     * Check if webhook can be processed.
     */
    public function canProcess(): bool
    {
        return in_array($this, [self::PENDING, self::FAILED], true);
    }
}
