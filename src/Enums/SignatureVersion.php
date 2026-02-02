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
     * HMAC-SHA256 cryptographic signature algorithm (version 1).
     *
     * Uses symmetric key authentication with SHA-256 hashing. Both sender
     * and receiver share the same secret key for signing and verification.
     * Standard and widely supported signature method for webhook validation.
     */
    case V1_HMAC = 'v1';

    /**
     * Ed25519 elliptic curve signature algorithm (version 1a).
     *
     * Uses asymmetric public-key cryptography based on Curve25519. Provides
     * enhanced security through private key signing and public key verification,
     * eliminating the need to share secret keys between parties.
     */
    case V1A_ED25519 = 'v1a';

    /**
     * Parse and extract the signature version from a webhook signature header.
     *
     * Analyzes the signature header format to determine which cryptographic
     * algorithm was used for signing. Returns null if the header format
     * does not match any supported version.
     *
     * @param string $header The raw signature header value from the webhook request,
     *                       expected to start with version prefix like "v1," or "v1a,"
     *                       followed by the actual signature value
     *
     * @return null|self The detected signature version enum case, or null if the
     *                   header format is unrecognized or uses an unsupported version
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
     * Get the version-specific prefix string for signature headers.
     *
     * Returns the signature version identifier followed by a comma delimiter,
     * which is used when constructing the signature header value according
     * to the Standard Webhooks specification format.
     *
     * @return string The version prefix with trailing comma (e.g., "v1," or "v1a,")
     *                ready for concatenation with the signature value
     */
    public function prefix(): string
    {
        return $this->value.',';
    }

    /**
     * Determine if this signature version uses HMAC-SHA256 algorithm.
     *
     * @return bool True if this is the v1 HMAC version, false otherwise
     */
    public function isHmac(): bool
    {
        return $this === self::V1_HMAC;
    }

    /**
     * Determine if this signature version uses Ed25519 algorithm.
     *
     * @return bool True if this is the v1a Ed25519 version, false otherwise
     */
    public function isEd25519(): bool
    {
        return $this === self::V1A_ED25519;
    }
}
