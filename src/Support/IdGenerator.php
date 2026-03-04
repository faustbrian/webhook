<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Support;

use Illuminate\Support\Str;

/**
 * Generates unique webhook IDs compliant with Standard Webhooks spec.
 *
 * Standard Webhooks requires webhook-id to be unique and used as an idempotency
 * key for preventing duplicate webhook deliveries. This implementation uses ULIDs
 * (Universally Unique Lexicographically Sortable Identifiers) which provide both
 * uniqueness guarantees and timestamp-based sorting capabilities. ULIDs are
 * 26-character case-insensitive strings that encode a 48-bit timestamp and
 * 80 bits of randomness.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class IdGenerator
{
    /**
     * Generate a unique webhook identifier using ULID format.
     *
     * Creates a new ULID containing the current timestamp and random data,
     * ensuring uniqueness across distributed systems while maintaining
     * lexicographic sortability by creation time.
     *
     * @return string 26-character ULID string suitable for webhook identification
     */
    public static function generate(): string
    {
        return (string) Str::ulid();
    }

    /**
     * Validate whether a string is a properly formatted ULID.
     *
     * @param  string $id String to validate as ULID format
     * @return bool   True if the string is a valid ULID, false otherwise
     */
    public static function isValid(string $id): bool
    {
        return Str::isUlid($id);
    }
}
