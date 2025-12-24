<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Enums;

use function str_starts_with;

/**
 * Standard Webhooks signature version specification.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md
 */
enum SignatureVersion: string
{
    /**
     * HMAC-SHA256 signature (v1).
     */
    case V1_HMAC = 'v1';

    /**
     * Ed25519 signature (v1a).
     */
    case V1A_ED25519 = 'v1a';

    /**
     * Parse signature version from header value.
     */
    public static function fromHeader(string $header): ?self
    {
        if (str_starts_with($header, 'v1a,')) {
            return self::V1A_ED25519;
        }

        if (str_starts_with($header, 'v1,')) {
            return self::V1_HMAC;
        }

        return null;
    }

    /**
     * Get the signature prefix for headers.
     */
    public function prefix(): string
    {
        return $this->value.',';
    }

    /**
     * Check if this is HMAC version.
     */
    public function isHmac(): bool
    {
        return $this === self::V1_HMAC;
    }

    /**
     * Check if this is Ed25519 version.
     */
    public function isEd25519(): bool
    {
        return $this === self::V1A_ED25519;
    }
}
