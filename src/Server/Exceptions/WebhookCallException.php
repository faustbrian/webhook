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
use Throwable;

use function sprintf;

/**
 * Thrown when webhook dispatch fails.
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookCallException extends RuntimeException implements WebhookException
{
    public static function dispatchFailed(string $url, ?Throwable $previous = null): self
    {
        return new self('Failed to dispatch webhook to: '.$url, 0, $previous);
    }

    public static function httpError(string $url, int $statusCode, string $response): self
    {
        return new self(sprintf('Webhook call to %s failed with HTTP %d: %s', $url, $statusCode, $response));
    }
}
