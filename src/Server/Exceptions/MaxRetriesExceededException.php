<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Exceptions;

use Cline\Webhook\Exceptions\WebhookException;
use RuntimeException;

use function sprintf;

/**
 * Thrown when maximum retry attempts are exceeded.
 * @author Brian Faust <brian@cline.sh>
 */
final class MaxRetriesExceededException extends RuntimeException implements WebhookException
{
    public static function make(int $maxRetries, string $url): self
    {
        return new self(sprintf('Maximum retry attempts (%d) exceeded for webhook: %s', $maxRetries, $url));
    }
}
