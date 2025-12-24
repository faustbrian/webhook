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
 * Standard Webhooks requires webhook-id to be unique and used as an idempotency key.
 * We use ULIDs for lexicographically sortable, timestamp-based unique identifiers.
 * @author Brian Faust <brian@cline.sh>
 */
final class IdGenerator
{
    /**
     * Generate a unique webhook ID.
     */
    public static function generate(): string
    {
        return (string) Str::ulid();
    }

    /**
     * Validate a webhook ID format.
     */
    public static function isValid(string $id): bool
    {
        return Str::isUlid($id);
    }
}
