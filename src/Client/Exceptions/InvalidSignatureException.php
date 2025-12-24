<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Exceptions;

use Cline\Webhook\Exceptions\WebhookException;
use RuntimeException;

/**
 * Thrown when webhook signature verification fails.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidSignatureException extends RuntimeException implements WebhookException
{
    public static function make(string $webhookId): self
    {
        return new self('Invalid webhook signature for webhook ID: '.$webhookId);
    }
}
