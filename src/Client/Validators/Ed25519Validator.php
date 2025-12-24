<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Client\Validators;

use Cline\Webhook\Client\Contracts\SignatureValidator;
use Cline\Webhook\Client\Exceptions\InvalidSignatureException;
use Cline\Webhook\Support\TimestampValidator;
use Illuminate\Http\Request;
use Throwable;

use function base64_decode;
use function explode;
use function sodium_crypto_sign_verify_detached;
use function sprintf;
use function str_contains;

/**
 * Ed25519 signature validator implementing the Standard Webhooks specification.
 *
 * Validates webhook signatures using Ed25519 public-key cryptography with the
 * v1a signature scheme. Provides strong cryptographic verification with smaller
 * keys and faster performance compared to RSA. Validates timestamp to prevent
 * replay attacks according to Standard Webhooks spec.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://www.standardwebhooks.com/
 * @psalm-immutable
 */
final readonly class Ed25519Validator implements SignatureValidator
{
    /**
     * Create a new Ed25519 signature validator.
     *
     * @param string             $publicKey          Base64-encoded Ed25519 public key for signature verification.
     *                                               Must be a valid 32-byte Ed25519 public key encoded in base64.
     * @param TimestampValidator $timestampValidator Validator for webhook timestamp to prevent
     *                                               replay attacks. Defaults to standard validator
     *                                               with 5-minute tolerance window.
     */
    public function __construct(
        private string $publicKey,
        private TimestampValidator $timestampValidator = new TimestampValidator(
        ),
    ) {}

    /**
     * Verify webhook signature using Ed25519 cryptography.
     *
     * Validates the webhook signature by:
     * 1. Extracting webhook-id, webhook-timestamp, and webhook-signature headers
     * 2. Validating timestamp is within acceptable tolerance to prevent replay attacks
     * 3. Building signed content in format: {id}.{timestamp}.{payload}
     * 4. Verifying at least one v1a signature matches using Ed25519
     *
     * @param  Request                                                    $request HTTP request containing webhook headers and payload
     * @param  string                                                     $secret  Not used for Ed25519 (uses public key instead) but required by interface
     * @throws \Cline\Webhook\Exceptions\InvalidWebhookTimestampException When timestamp is outside acceptable tolerance
     * @throws InvalidSignatureException                                  When no valid v1a signature is found
     */
    public function verify(Request $request, string $secret): void
    {
        /** @var string $webhookId */
        $webhookId = $request->header('webhook-id');

        /** @var string $timestampHeader */
        $timestampHeader = $request->header('webhook-timestamp');
        $timestamp = (int) $timestampHeader;

        /** @var string $signatures */
        $signatures = $request->header('webhook-signature');

        // Validate timestamp to prevent replay attacks
        $this->timestampValidator->validate($timestamp);

        // Get payload
        $payload = $request->getContent();

        // Build signed content: {id}.{timestamp}.{payload}
        $signedContent = sprintf('%s.%d.%s', $webhookId, $timestamp, $payload);

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
     * Check if webhook signature is valid without throwing exceptions.
     *
     * Convenience method that calls verify() and returns true on success,
     * false on any validation failure. Useful for conditional logic without
     * exception handling.
     *
     * @param  Request $request HTTP request containing webhook headers and payload
     * @param  string  $secret  Not used for Ed25519 but required by interface
     * @return bool    True if signature is valid, false otherwise
     */
    public function isValid(Request $request, string $secret): bool
    {
        try {
            $this->verify($request, $secret);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Parse signature header into structured array of versions and signatures.
     *
     * Parses Standard Webhooks signature header format: "v1a,sig1 v1a,sig2"
     * into associative array grouped by version identifier. Supports multiple
     * signature versions in a single header for algorithm transitions.
     *
     * @param  string                       $header Raw webhook-signature header value
     * @return array<string, array<string>> Signatures grouped by version (e.g., ['v1a' => ['sig1', 'sig2']])
     */
    private function parseSignatures(string $header): array
    {
        $signatures = [];
        $parts = explode(' ', $header);

        foreach ($parts as $part) {
            if (!str_contains($part, ',')) {
                continue;
            }

            [$version, $signature] = explode(',', $part, 2);
            $signatures[$version][] = $signature;
        }

        return $signatures;
    }

    /**
     * Verify a single signature using Ed25519 cryptographic algorithm.
     *
     * Decodes the base64-encoded public key and signature, then verifies the
     * signature using libsodium's Ed25519 implementation. Returns false if
     * decoding fails or signature is invalid, true if signature is valid.
     *
     * @param  string $signedContent     The signed content string in format: {id}.{timestamp}.{payload}
     * @param  string $receivedSignature Base64-encoded Ed25519 signature to verify
     * @return bool   True if signature is valid, false otherwise
     */
    private function verifySignature(string $signedContent, string $receivedSignature): bool
    {
        $decodedKey = base64_decode($this->publicKey, true);
        $decodedSignature = base64_decode($receivedSignature, true);

        if ($decodedKey === false || $decodedSignature === false || $decodedKey === '' || $decodedSignature === '') {
            return false;
        }

        return sodium_crypto_sign_verify_detached(
            $decodedSignature,
            $signedContent,
            $decodedKey,
        );
    }
}
