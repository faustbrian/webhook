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
 * @author Brian Faust <brian@cline.sh>
 * @see https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md
 * @psalm-immutable
 */
final readonly class Ed25519Signer implements Signer
{
    public function __construct(
        private string $privateKey,
    ) {}

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function version(): SignatureVersion
    {
        return SignatureVersion::V1A_ED25519;
    }
}
