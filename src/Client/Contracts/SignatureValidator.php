<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Contracts;

use Cline\Webhook\Client\Exceptions\InvalidSignatureException;
use Cline\Webhook\Exceptions\Client\InvalidTimestampException;
use Illuminate\Http\Request;

/**
 * Defines the contract for validating webhook request signatures.
 *
 * Implementers of this interface verify the cryptographic signatures
 * of incoming webhook requests to ensure authenticity and prevent
 * unauthorized or tampered requests from being processed. This is a
 * critical security layer for webhook handling.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface SignatureValidator
{
    /**
     * Verifies the webhook request signature and throws on failure.
     *
     * This method performs cryptographic validation of the webhook signature
     * using the provided secret. It should verify both the signature format
     * and authenticity, and may also validate timestamp freshness to prevent
     * replay attacks.
     *
     * @param Request $request The incoming HTTP request containing the webhook
     *                         payload and signature headers to validate
     * @param string  $secret  The shared secret key used to verify the HMAC
     *                         signature against the webhook payload
     *
     * @throws InvalidSignatureException When the signature is missing, malformed,
     *                                   or does not match the expected value
     * @throws InvalidTimestampException When the webhook timestamp is outside the
     *                                   acceptable time window for replay protection
     */
    public function verify(Request $request, string $secret): void;

    /**
     * Checks if the webhook signature is valid without throwing exceptions.
     *
     * This is a non-throwing alternative to verify() that returns a boolean
     * result. Useful for conditional logic where exception handling is not
     * desired, such as logging or metrics collection.
     *
     * @param Request $request The incoming HTTP request containing the webhook
     *                         payload and signature headers to validate
     * @param string  $secret  The shared secret key used to verify the HMAC
     *                         signature against the webhook payload
     *
     * @return bool Returns true if the signature is valid, false otherwise
     */
    public function isValid(Request $request, string $secret): bool;
}
