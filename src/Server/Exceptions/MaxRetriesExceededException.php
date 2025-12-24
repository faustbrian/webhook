<?php

declare(strict_types=1);

namespace Cline\Webhook\Server\Exceptions;

use Cline\Webhook\Exceptions\WebhookException;

/**
 * Thrown when maximum retry attempts are exceeded.
 */
final class MaxRetriesExceededException extends WebhookException
{
    public static function make(int $maxRetries, string $url): self
    {
        return new self("Maximum retry attempts ({$maxRetries}) exceeded for webhook: {$url}");
    }
}
