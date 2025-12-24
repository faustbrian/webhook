<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Contracts;

use Illuminate\Http\Request;

/**
 * Defines webhook signature verification.
 */
interface SignatureValidator
{
    /**
     * Verify webhook signature.
     *
     * @throws \Cline\Webhook\Client\Exceptions\InvalidSignatureException
     * @throws \Cline\Webhook\Exceptions\Client\InvalidTimestampException
     */
    public function verify(Request $request, string $secret): void;

    /**
     * Check if signature is valid without throwing.
     */
    public function isValid(Request $request, string $secret): bool;
}
