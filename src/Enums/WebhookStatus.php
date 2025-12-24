<?php

declare(strict_types=1);

namespace Cline\Webhook\Enums;

/**
 * Webhook processing status.
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
        return \in_array($this, [self::PROCESSED, self::FAILED], true);
    }

    /**
     * Check if webhook can be processed.
     */
    public function canProcess(): bool
    {
        return \in_array($this, [self::PENDING, self::FAILED], true);
    }
}
