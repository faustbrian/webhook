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
 * Exception thrown when webhook timestamp is in the future.
 * @author Brian Faust <brian@cline.sh>
 */
final class FutureTimestampException extends InvalidTimestampException
{
    public static function fromTimestamps(int $timestamp, int $now): self
    {
        return new self(sprintf('Webhook timestamp (%d) is in the future (current: %d)', $timestamp, $now));
    }
}
