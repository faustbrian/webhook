<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Exceptions;

use Cline\Webhook\Exceptions\WebhookException;

/**
 * Thrown when webhook signature verification fails.
 */
final class InvalidSignatureException extends WebhookException
{
    public static function make(string $webhookId): self
    {
        return new self("Invalid webhook signature for webhook ID: {$webhookId}");
    }
}
