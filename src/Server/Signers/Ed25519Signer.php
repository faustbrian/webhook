<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Signers;

use Cline\Webhook\Enums\SignatureVersion;
use Cline\Webhook\Exceptions\Server\InvalidEd25519PrivateKeyException;
use Cline\Webhook\Server\Contracts\Signer;

use function base64_decode;
use function base64_encode;
use function sodium_crypto_sign_detached;
use function sprintf;
use function throw_if;

/**
 * Ed25519 signature implementation per Standard Webhooks spec.
 *
 * Provides cryptographic signing using Ed25519 public-key signature system,
 * offering stronger security guarantees than HMAC-based signatures. Uses the
 * libsodium extension for cryptographic operations and returns signatures in
 * the v1a signature format with base64-encoded signature values.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md
 * @psalm-immutable
 */
final readonly class Ed25519Signer implements Signer
{
    /**
     * Create a new Ed25519 signature provider.
     *
     * @param string $privateKey Base64-encoded Ed25519 private key used for signing
     *                           webhook payloads. Must be a valid 64-byte Ed25519
     *                           private key encoded in base64 format. Invalid keys
     *                           will cause signing operations to fail.
     */
    public function __construct(
        private string $privateKey,
    ) {}

    /**
     * Generate cryptographic signature for webhook payload using Ed25519.
     *
     * Creates a detached Ed25519 signature over the standard webhook signing
     * content (webhook-id.timestamp.payload) using the configured private key.
     * Returns the signature in Standard Webhooks format: "v1a,base64_signature".
     *
     * @param  string                            $webhookId Unique identifier for the webhook message
     * @param  int                               $timestamp Unix timestamp when the webhook was sent
     * @param  string                            $payload   JSON-encoded webhook payload to sign
     * @throws InvalidEd25519PrivateKeyException When the private key format is invalid or cannot be decoded
     * @return string                            Formatted signature string in "v1a,base64_signature" format
     */
    public function sign(string $webhookId, int $timestamp, string $payload): string
    {
        $signedContent = sprintf('%s.%d.%s', $webhookId, $timestamp, $payload);

        // Decode the base64-encoded private key
        $decodedKey = base64_decode($this->privateKey, true);

        throw_if($decodedKey === false, InvalidEd25519PrivateKeyException::invalidFormat());

        // Sign using sodium extension
        /** @var non-empty-string $decodedKey */
        $signature = sodium_crypto_sign_detached($signedContent, $decodedKey);
        $encoded = base64_encode($signature);

        return $this->version()->value.','.$encoded;
    }

    /**
     * Returns the signature version identifier for Ed25519 signatures.
     *
     * @return SignatureVersion Always returns V1A_ED25519 version identifier
     */
    public function version(): SignatureVersion
    {
        return SignatureVersion::V1A_ED25519;
    }
}
