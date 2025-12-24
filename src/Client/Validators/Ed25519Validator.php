<?php

declare(strict_types=1);

namespace Cline\Webhook\Client\Validators;

use Cline\Webhook\Client\Contracts\SignatureValidator;
use Cline\Webhook\Client\Exceptions\InvalidSignatureException;
use Cline\Webhook\Support\TimestampValidator;
use Illuminate\Http\Request;

/**
 * Ed25519 signature validator per Standard Webhooks spec.
 */
final class Ed25519Validator implements SignatureValidator
{
    private readonly TimestampValidator $timestampValidator;

    public function __construct(
        private readonly string $publicKey,
        ?TimestampValidator $timestampValidator = null,
    ) {
        $this->timestampValidator = $timestampValidator ?? new TimestampValidator();
    }

    /**
     * {@inheritDoc}
     */
    public function verify(Request $request, string $secret): void
    {
        $webhookId = $request->header('webhook-id');
        $timestamp = (int) $request->header('webhook-timestamp');
        $signatures = $request->header('webhook-signature');

        // Validate timestamp to prevent replay attacks
        $this->timestampValidator->validate($timestamp);

        // Get payload
        $payload = $request->getContent();

        // Build signed content: {id}.{timestamp}.{payload}
        $signedContent = "{$webhookId}.{$timestamp}.{$payload}";

        // Parse signatures
        $receivedSignatures = $this->parseSignatures($signatures);

        // Verify at least one v1a signature matches
        foreach ($receivedSignatures as $version => $sigs) {
            if ($version !== 'v1a') {
                continue;
            }

            foreach ($sigs as $sig) {
                if ($this->verifySignature($signedContent, $sig)) {
                    return;
                }
            }
        }

        throw InvalidSignatureException::make($webhookId);
    }

    /**
     * {@inheritDoc}
     */
    public function isValid(Request $request, string $secret): bool
    {
        try {
            $this->verify($request, $secret);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Parse signature header into versions and signatures.
     *
     * @return array<string, array<string>>
     */
    private function parseSignatures(string $header): array
    {
        $signatures = [];
        $parts = \explode(' ', $header);

        foreach ($parts as $part) {
            if (! \str_contains($part, ',')) {
                continue;
            }

            [$version, $signature] = \explode(',', $part, 2);
            $signatures[$version][] = $signature;
        }

        return $signatures;
    }

    /**
     * Verify signature using Ed25519.
     */
    private function verifySignature(string $signedContent, string $receivedSignature): bool
    {
        $decodedKey = \base64_decode($this->publicKey, true);
        $decodedSignature = \base64_decode($receivedSignature, true);

        if ($decodedKey === false || $decodedSignature === false) {
            return false;
        }

        return \sodium_crypto_sign_verify_detached(
            $decodedSignature,
            $signedContent,
            $decodedKey
        );
    }
}
