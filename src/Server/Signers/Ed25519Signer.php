<?php

declare(strict_types=1);

namespace Cline\Webhook\Server\Signers;

use Cline\Webhook\Enums\SignatureVersion;
use Cline\Webhook\Server\Contracts\Signer;

/**
 * Ed25519 signature implementation per Standard Webhooks spec.
 *
 * @see https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md
 */
final class Ed25519Signer implements Signer
{
    public function __construct(
        private readonly string $privateKey,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function sign(string $webhookId, int $timestamp, string $payload): string
    {
        $signedContent = "{$webhookId}.{$timestamp}.{$payload}";

        // Decode the base64-encoded private key
        $decodedKey = \base64_decode($this->privateKey, true);

        if ($decodedKey === false) {
            throw new \InvalidArgumentException('Invalid Ed25519 private key format');
        }

        // Sign using sodium extension
        $signature = \sodium_crypto_sign_detached($signedContent, $decodedKey);
        $encoded = \base64_encode($signature);

        return $this->version()->value.','.$encoded;
    }

    /**
     * {@inheritDoc}
     */
    public function version(): SignatureVersion
    {
        return SignatureVersion::V1A_ED25519;
    }
}
