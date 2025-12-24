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

use function base64_encode;
use function explode;
use function hash_equals;
use function hash_hmac;
use function sprintf;
use function str_contains;

/**
 * HMAC-SHA256 signature validator implementing the Standard Webhooks specification.
 *
 * Validates webhook signatures using HMAC-SHA256 symmetric cryptography with the
 * v1 signature scheme. Uses a shared secret for both signing and verification,
 * making it simpler to implement than asymmetric schemes. Validates timestamp
 * to prevent replay attacks according to Standard Webhooks spec.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://www.standardwebhooks.com/
 * @psalm-immutable
 */
final readonly class HmacValidator implements SignatureValidator
{
    /**
     * Create a new HMAC-SHA256 signature validator.
     *
     * @param TimestampValidator $timestampValidator Validator for webhook timestamp to prevent
     *                                               replay attacks. Defaults to standard validator
     *                                               with 5-minute tolerance window.
     */
    public function __construct(
        private TimestampValidator $timestampValidator = new TimestampValidator(
        ),
    ) {}

    /**
     * Verify webhook signature using HMAC-SHA256 cryptography.
     *
     * Validates the webhook signature by:
     * 1. Extracting webhook-id, webhook-timestamp, and webhook-signature headers
     * 2. Validating timestamp is within acceptable tolerance to prevent replay attacks
     * 3. Building signed content in format: {id}.{timestamp}.{payload}
     * 4. Verifying at least one v1 signature matches using HMAC-SHA256 and shared secret
     *
     * @param  Request                                                    $request HTTP request containing webhook headers and payload
     * @param  string                                                     $secret  Shared secret key used for HMAC signature verification
     * @throws \Cline\Webhook\Exceptions\InvalidWebhookTimestampException When timestamp is outside acceptable tolerance
     * @throws InvalidSignatureException                                  When no valid v1 signature is found
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

        // Parse signatures (can have multiple versions: "v1,sig1 v1,sig2")
        $receivedSignatures = $this->parseSignatures($signatures);

        // Verify at least one v1 signature matches
        foreach ($receivedSignatures as $version => $sigs) {
            if ($version !== 'v1') {
                continue;
            }

            foreach ($sigs as $sig) {
                if ($this->verifySignature($signedContent, $sig, $secret)) {
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
     * @param  string  $secret  Shared secret key used for HMAC signature verification
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
     * Parses Standard Webhooks signature header format: "v1,sig1 v1,sig2"
     * into associative array grouped by version identifier. Supports multiple
     * signature versions in a single header for algorithm transitions.
     *
     * @param  string                       $header Raw webhook-signature header value
     * @return array<string, array<string>> Signatures grouped by version (e.g., ['v1' => ['sig1', 'sig2']])
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
     * Verify a single signature using HMAC-SHA256 with timing-safe comparison.
     *
     * Generates expected HMAC signature using SHA256 and the shared secret,
     * then compares it to the received signature using hash_equals to prevent
     * timing attacks. The comparison is constant-time to avoid leaking
     * information about the signature through timing analysis.
     *
     * @param  string $signedContent     The signed content string in format: {id}.{timestamp}.{payload}
     * @param  string $receivedSignature Base64-encoded HMAC signature from webhook header
     * @param  string $secret            Shared secret key used for HMAC generation
     * @return bool   True if signatures match, false otherwise
     */
    private function verifySignature(string $signedContent, string $receivedSignature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $signedContent, $secret, true);
        $expectedEncoded = base64_encode($expectedSignature);

        return hash_equals($expectedEncoded, $receivedSignature);
    }
}
