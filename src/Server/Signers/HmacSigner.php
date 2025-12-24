<?php

declare(strict_types=1);

namespace Cline\Webhook\Server\Signers;

use Cline\Webhook\Enums\SignatureVersion;
use Cline\Webhook\Server\Contracts\Signer;

/**
 * HMAC-SHA256 signature implementation per Standard Webhooks spec.
 *
 * @see https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md
 */
final class HmacSigner implements Signer
{
    public function __construct(
        private readonly string $secret,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function sign(string $webhookId, int $timestamp, string $payload): string
    {
        $signedContent = "{$webhookId}.{$timestamp}.{$payload}";
        $signature = \hash_hmac('sha256', $signedContent, $this->secret, true);
        $encoded = \base64_encode($signature);

        return $this->version()->value.','.$encoded;
    }

    /**
     * {@inheritDoc}
     */
    public function version(): SignatureVersion
    {
        return SignatureVersion::V1_HMAC;
    }
}
