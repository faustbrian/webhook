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
 * Thrown when a webhook call cannot be found in the database.
 *
 * This exception indicates that a lookup for a stored webhook call by its
 * identifier failed to find a matching record. This may occur when attempting
 * to process, retry, or reference a webhook that was never stored, has been
 * deleted, or when using an incorrect identifier.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookNotFoundException extends RuntimeException implements WebhookException
{
    /**
     * Creates a new exception for a webhook that cannot be found.
     *
     * This static factory method provides a consistent way to create instances
     * with a standardized error message format that includes the webhook
     * identifier that was searched for.
     *
     * @param string $webhookId The identifier of the webhook that could not be
     *                          found in the database, used for debugging and
     *                          error reporting purposes
     *
     * @return self The configured exception instance ready to be thrown
     */
    public static function make(string $webhookId): self
    {
        return new self('Webhook not found: '.$webhookId);
    }
}
