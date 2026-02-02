<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Signers;

use Cline\Webhook\Enums\SignatureVersion;
use Cline\Webhook\Server\Contracts\Signer;

use function base64_encode;
use function hash_hmac;
use function sprintf;

/**
 * HMAC-SHA256 signature implementation per Standard Webhooks spec.
 *
 * Provides symmetric cryptographic signing using HMAC with SHA-256 hashing
 * algorithm. This is the default signing method for webhooks and offers good
 * security for scenarios where both sender and receiver can securely share a
 * secret key. Returns signatures in the v1 format with base64-encoded values.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md
 * @psalm-immutable
 */
final readonly class HmacSigner implements Signer
{
    /**
     * Create a new HMAC signature provider.
     *
     * @param string $secret Shared secret key used for HMAC signature generation.
     *                       Should be a cryptographically secure random string stored
     *                       securely on both sender and receiver sides. Recommended
     *                       minimum length is 32 bytes for adequate security.
     */
    public function __construct(
        private string $secret,
    ) {}

    /**
     * Generate HMAC-SHA256 signature for webhook payload.
     *
     * Creates an HMAC signature over the standard webhook signing content
     * (webhook-id.timestamp.payload) using SHA-256 and the configured secret.
     * Returns the signature in Standard Webhooks format: "v1,base64_signature".
     *
     * @param  string $webhookId Unique identifier for the webhook message
     * @param  int    $timestamp Unix timestamp when the webhook was sent
     * @param  string $payload   JSON-encoded webhook payload to sign
     * @return string Formatted signature string in "v1,base64_signature" format
     */
    public function sign(string $webhookId, int $timestamp, string $payload): string
    {
        $signedContent = sprintf('%s.%d.%s', $webhookId, $timestamp, $payload);
        $signature = hash_hmac('sha256', $signedContent, $this->secret, true);
        $encoded = base64_encode($signature);

        return $this->version()->value.','.$encoded;
    }

    /**
     * Returns the signature version identifier for HMAC signatures.
     *
     * @return SignatureVersion Always returns V1_HMAC version identifier
     */
    public function version(): SignatureVersion
    {
        return SignatureVersion::V1_HMAC;
    }
}
