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
 * Defines webhook signature verification.
 * @author Brian Faust <brian@cline.sh>
 */
interface SignatureValidator
{
    /**
     * Verify webhook signature.
     *
     * @throws InvalidSignatureException
     * @throws InvalidTimestampException
     */
    public function verify(Request $request, string $secret): void;

    /**
     * Check if signature is valid without throwing.
     */
    public function isValid(Request $request, string $secret): bool;
}
