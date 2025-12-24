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

/**
 * HMAC-SHA256 signature implementation per Standard Webhooks spec.
 *
 * @author Brian Faust <brian@cline.sh>
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
        $signature = hash_hmac('sha256', $signedContent, $this->secret, true);
        $encoded = base64_encode($signature);

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
