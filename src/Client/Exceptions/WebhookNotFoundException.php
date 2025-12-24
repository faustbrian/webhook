<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Exceptions;

use Cline\Webhook\Exceptions\WebhookException;

/**
 * Thrown when a webhook call cannot be found in database.
 * @author Brian Faust <brian@cline.sh>
 */
final class WebhookNotFoundException extends WebhookException
{
    public static function make(string $webhookId): self
    {
        return new self("Webhook not found: {$webhookId}");
    }
}
