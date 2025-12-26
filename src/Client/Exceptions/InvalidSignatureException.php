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
 *
 * This exception indicates that the cryptographic signature of an incoming
 * webhook request does not match the expected value, suggesting either a
 * configuration mismatch, tampered payload, or unauthorized request. This
 * is a security-critical exception that should trigger appropriate logging
 * and monitoring.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidSignatureException extends RuntimeException implements WebhookException
{
    /**
     * Creates a new exception for an invalid webhook signature.
     *
     * This static factory method provides a consistent way to create instances
     * with a standardized error message format that includes the webhook
     * identifier for debugging and audit purposes.
     *
     * @param string $webhookId The identifier of the webhook that failed signature
     *                          verification, used for logging and security analysis
     *
     * @return self The configured exception instance ready to be thrown
     */
    public static function make(string $webhookId): self
    {
        return new self('Invalid webhook signature for webhook ID: '.$webhookId);
    }
}
