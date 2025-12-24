<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Support;

use Cline\Webhook\Exceptions\Client\InvalidTimestampException;

use function time;

/**
 * Validates webhook timestamps for replay attack prevention.
 *
 * Standard Webhooks requires timestamp validation to prevent replay attacks.
 * Timestamps must be within a configured tolerance window.
 * @author Brian Faust <brian@cline.sh>
 */
final class TimestampValidator
{
    /**
     * @param int $toleranceSeconds Maximum age in seconds for a webhook (default: 5 minutes)
     */
    public function __construct(
        private readonly int $toleranceSeconds = 300,
    ) {}

    /**
     * Generate current timestamp.
     */
    public static function generate(): int
    {
        return time();
    }

    /**
     * Validate a timestamp is within tolerance.
     *
     * @throws InvalidTimestampException
     */
    public function validate(int $timestamp): void
    {
        $now = time();
        $age = $now - $timestamp;

        // Timestamp is in the future
        if ($age < 0) {
            throw InvalidTimestampException::future($timestamp, $now);
        }

        // Timestamp is too old
        if ($age > $this->toleranceSeconds) {
            throw InvalidTimestampException::expired($timestamp, $now, $this->toleranceSeconds);
        }
    }

    /**
     * Check if timestamp is valid without throwing.
     */
    public function isValid(int $timestamp): bool
    {
        try {
            $this->validate($timestamp);

            return true;
        } catch (InvalidTimestampException) {
            return false;
        }
    }
}
