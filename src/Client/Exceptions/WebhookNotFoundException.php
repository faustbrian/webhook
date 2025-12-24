<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Exceptions;

use Cline\Webhook\Exceptions\WebhookException;

/**
 * Thrown when a webhook call cannot be found in database.
 */
final class WebhookNotFoundException extends WebhookException
{
    public static function make(string $webhookId): self
    {
        return new self("Webhook not found: {$webhookId}");
    }
}
