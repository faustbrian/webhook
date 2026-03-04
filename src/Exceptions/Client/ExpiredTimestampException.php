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
 * Exception thrown when webhook timestamp exceeds the allowed age tolerance.
 *
 * Indicates the webhook request is stale and should be rejected to prevent
 * replay attacks. The timestamp validation ensures webhooks are processed
 * within a reasonable time window after being sent by the server.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ExpiredTimestampException extends InvalidTimestampException
{
    /**
     * Create exception for timestamp that exceeds maximum allowed age.
     *
     * Generates a detailed error message showing the exact timestamp values
     * and tolerance settings to aid in debugging timestamp validation failures.
     * The age calculation demonstrates how far outside the tolerance window
     * the webhook timestamp falls.
     *
     * @param int $timestamp The Unix timestamp from the webhook header that was
     *                       rejected for being too old, representing when the
     *                       webhook was originally signed by the sender
     * @param int $now       The current Unix timestamp at validation time, used
     *                       as the reference point for calculating webhook age
     * @param int $tolerance The maximum allowed age in seconds before a webhook
     *                       is considered expired and rejected for security reasons
     *
     * @return self A new exception instance with formatted message containing
     *              the timestamp, calculated age, and configured tolerance value
     */
    public static function fromTimestamps(int $timestamp, int $now, int $tolerance): self
    {
        $age = $now - $timestamp;

        return new self(sprintf('Webhook timestamp (%d) is too old. Age: %ds, Tolerance: %ds', $timestamp, $age, $tolerance));
    }
}
