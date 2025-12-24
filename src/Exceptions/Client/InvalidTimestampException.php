<?php

declare(strict_types=1);

namespace Cline\Webhook\Exceptions\Client;

use Cline\Webhook\Exceptions\WebhookException;

/**
 * Thrown when webhook timestamp is invalid or outside tolerance window.
 */
final class InvalidTimestampException extends WebhookException
{
    public static function future(int $timestamp, int $now): self
    {
        return new self("Webhook timestamp ({$timestamp}) is in the future (current: {$now})");
    }

    public static function expired(int $timestamp, int $now, int $tolerance): self
    {
        $age = $now - $timestamp;

        return new self("Webhook timestamp ({$timestamp}) is too old. Age: {$age}s, Tolerance: {$tolerance}s");
    }
}
