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
 * Exception thrown when webhook timestamp is outside tolerance window.
 * @author Brian Faust <brian@cline.sh>
 */
final class ExpiredTimestampException extends InvalidTimestampException
{
    public static function fromTimestamps(int $timestamp, int $now, int $tolerance): self
    {
        $age = $now - $timestamp;

        return new self(sprintf('Webhook timestamp (%d) is too old. Age: %ds, Tolerance: %ds', $timestamp, $age, $tolerance));
    }
}
