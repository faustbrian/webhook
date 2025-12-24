<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Exceptions;

use Cline\Webhook\Exceptions\WebhookException;

/**
 * Thrown when maximum retry attempts are exceeded.
 * @author Brian Faust <brian@cline.sh>
 */
final class MaxRetriesExceededException extends WebhookException
{
    public static function make(int $maxRetries, string $url): self
    {
        return new self("Maximum retry attempts ({$maxRetries}) exceeded for webhook: {$url}");
    }
}
