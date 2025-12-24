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
 * HMAC-SHA256 signature validator per Standard Webhooks spec.
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class HmacValidator implements SignatureValidator
{
    public function __construct(
        private TimestampValidator $timestampValidator = new TimestampValidator(
        ),
    ) {}

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * Parse signature header into versions and signatures.
     *
     * @return array<string, array<string>>
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
     * Verify single signature using timing-safe comparison.
     */
    private function verifySignature(string $signedContent, string $receivedSignature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $signedContent, $secret, true);
        $expectedEncoded = base64_encode($expectedSignature);

        return hash_equals($expectedEncoded, $receivedSignature);
    }
}
