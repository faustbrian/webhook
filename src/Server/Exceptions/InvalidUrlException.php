<?php

declare(strict_types=1);

namespace Cline\Webhook\Server\Exceptions;

use Cline\Webhook\Exceptions\WebhookException;

/**
 * Thrown when an invalid URL is provided for webhook dispatch.
 */
final class InvalidUrlException extends WebhookException
{
    public static function make(string $url): self
    {
        return new self("Invalid webhook URL: {$url}");
    }
}
