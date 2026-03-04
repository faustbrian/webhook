<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Support;

use Cline\Webhook\Exceptions\Client\ExpiredTimestampException;
use Cline\Webhook\Exceptions\Client\FutureTimestampException;
use Cline\Webhook\Exceptions\Client\InvalidTimestampException;
use Illuminate\Support\Facades\Date;

/**
 * Validates webhook timestamps for replay attack prevention.
 *
 * Standard Webhooks specification requires timestamp validation to prevent
 * replay attacks where an attacker reuses captured webhook payloads. This
 * validator ensures timestamps are recent (within tolerance window) and not
 * in the future. The default 5-minute tolerance balances security with clock
 * skew accommodation across distributed systems.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class TimestampValidator
{
    /**
     * Create a new timestamp validator.
     *
     * @param int $toleranceSeconds Maximum age in seconds for a valid webhook timestamp
     *                              (default: 300 - 5 minutes). Webhooks older than this
     *                              are rejected as potential replay attacks. Should be
     *                              set large enough to account for network delays and
     *                              clock skew, but small enough to limit replay window.
     */
    public function __construct(
        private int $toleranceSeconds = 300,
    ) {}

    /**
     * Generate a current Unix timestamp.
     *
     * @return int Unix timestamp representing the current moment in seconds since epoch
     */
    public static function generate(): int
    {
        return Date::now()->getTimestamp();
    }

    /**
     * Validate a webhook timestamp is within acceptable tolerance.
     *
     * Ensures the timestamp is neither in the future (clock skew protection)
     * nor too old (replay attack prevention). Throws specific exceptions for
     * different validation failures to provide clear error messaging.
     *
     * @param  int                       $timestamp Unix timestamp from the webhook to validate
     * @throws ExpiredTimestampException When the timestamp is older than tolerance allows
     * @throws FutureTimestampException  When the timestamp is in the future
     */
    public function validate(int $timestamp): void
    {
        $now = Date::now()->getTimestamp();
        $age = $now - $timestamp;

        // Timestamp is in the future
        if ($age < 0) {
            throw FutureTimestampException::fromTimestamps($timestamp, $now);
        }

        // Timestamp is too old
        if ($age > $this->toleranceSeconds) {
            throw ExpiredTimestampException::fromTimestamps($timestamp, $now, $this->toleranceSeconds);
        }
    }

    /**
     * Check if a timestamp is valid without throwing exceptions.
     *
     * @param  int  $timestamp Unix timestamp to validate
     * @return bool True if timestamp is valid, false if expired or in future
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
