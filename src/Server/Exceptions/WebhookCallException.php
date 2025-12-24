<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Exceptions;

use Cline\Webhook\Exceptions\WebhookException;
use Throwable;

/**
 * Thrown when webhook dispatch fails.
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookCallException extends WebhookException
{
    public static function dispatchFailed(string $url, ?Throwable $previous = null): self
    {
        return new self("Failed to dispatch webhook to: {$url}", 0, $previous);
    }

    public static function httpError(string $url, int $statusCode, string $response): self
    {
        return new self("Webhook call to {$url} failed with HTTP {$statusCode}: {$response}");
    }
}
