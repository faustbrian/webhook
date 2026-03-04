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
 * Exception thrown when Ed25519 private key format validation fails.
 *
 * Indicates the provided cryptographic key does not meet Ed25519 private key
 * requirements. This typically occurs during webhook server configuration when
 * the key is malformed, has incorrect encoding, or doesn't match the expected
 * 32-byte seed or 64-byte extended format required by the Ed25519 algorithm.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidEd25519PrivateKeyException extends InvalidArgumentException implements WebhookException
{
    /**
     * Create exception for Ed25519 private key with invalid structure.
     *
     * Factory method for generating consistent error messages when key
     * validation fails. This prevents webhook signing operations from
     * proceeding with invalid cryptographic material.
     *
     * @return self A new exception instance with standard error message
     *              indicating the key format validation failure
     */
    public static function invalidFormat(): self
    {
        return new self('Invalid Ed25519 private key format');
    }
}
