<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Exceptions\Client;

use function sprintf;

/**
 * Exception thrown when webhook timestamp is ahead of current server time.
 *
 * Indicates clock synchronization issues or potential timestamp manipulation.
 * Webhooks with future timestamps are rejected as they violate temporal
 * causality and may indicate security concerns or misconfigured systems.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FutureTimestampException extends InvalidTimestampException
{
    /**
     * Create exception for timestamp that is ahead of current server time.
     *
     * Generates an error message comparing the webhook timestamp against
     * the current server time to highlight the temporal inconsistency.
     * This typically indicates clock skew between sender and receiver.
     *
     * @param int $timestamp The Unix timestamp from the webhook header that was
     *                       rejected for being in the future, indicating when
     *                       the sender claims to have signed the webhook
     * @param int $now       The current Unix timestamp on the receiving server,
     *                       used as the temporal reference for validation
     *
     * @return self A new exception instance with formatted message showing
     *              both the future timestamp and current server time
     */
    public static function fromTimestamps(int $timestamp, int $now): self
    {
        return new self(sprintf('Webhook timestamp (%d) is in the future (current: %d)', $timestamp, $now));
    }
}
