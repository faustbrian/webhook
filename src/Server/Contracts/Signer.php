<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Webhook\Server\Contracts;

use Cline\Webhook\Enums\SignatureVersion;

/**
 * Defines webhook signature generation for secure webhook delivery.
 *
 * Implementations generate cryptographic signatures that allow webhook
 * receivers to verify the authenticity and integrity of webhook payloads.
 * Signatures prevent tampering and ensure webhooks originate from trusted sources.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Signer
{
    /**
     * Generate cryptographic signature for webhook payload.
     *
     * Creates a signature by combining the webhook ID, timestamp, and payload
     * using a cryptographic algorithm. The signature is formatted with a version
     * prefix to support multiple signature schemes.
     *
     * @param  string $webhookId Unique webhook identifier used as part of the signature input
     * @param  int    $timestamp Unix timestamp of when the webhook was created, prevents replay attacks
     * @param  string $payload   JSON-encoded webhook payload to be signed
     * @return string Versioned signature string in format "v1,<base64_signature>" or "v1a,<base64_signature>"
     */
    public function sign(string $webhookId, int $timestamp, string $payload): string;

    /**
     * Get the signature version used by this signer.
     *
     * @return SignatureVersion The signature algorithm version (v1 or v1a)
     */
    public function version(): SignatureVersion;
}
