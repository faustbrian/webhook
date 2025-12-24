<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Exceptions\Server;

use Cline\Webhook\Exceptions\WebhookException;
use InvalidArgumentException;

/**
 * Exception thrown when Ed25519 private key format is invalid.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidEd25519PrivateKeyException extends InvalidArgumentException implements WebhookException
{
    public static function invalidFormat(): self
    {
        return new self('Invalid Ed25519 private key format');
    }
}
