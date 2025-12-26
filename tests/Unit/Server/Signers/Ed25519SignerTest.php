<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Server\Signers;

use Cline\Webhook\Enums\SignatureVersion;
use Cline\Webhook\Exceptions\Server\InvalidEd25519PrivateKeyException;
use Cline\Webhook\Server\Signers\Ed25519Signer;
use SodiumException;

use function base64_decode;
use function base64_encode;
use function beforeEach;
use function describe;
use function expect;
use function explode;
use function it;
use function sodium_crypto_sign_keypair;
use function sodium_crypto_sign_publickey;
use function sodium_crypto_sign_secretkey;
use function sodium_crypto_sign_verify_detached;
use function sprintf;
use function str_repeat;

describe('Ed25519Signer', function (): void {
    beforeEach(function (): void {
        // Arrange - Generate valid Ed25519 keypair
        $keypair = sodium_crypto_sign_keypair();
        $this->privateKey = base64_encode(sodium_crypto_sign_secretkey($keypair));
        $this->publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));
        $this->signer = new Ed25519Signer($this->privateKey);
    });

    describe('Happy Paths', function (): void {
        it('signs webhook payload with valid Ed25519 signature', function (): void {
            // Arrange
            $webhookId = 'msg_test123';
            $timestamp = 1_234_567_890;
            $payload = '{"event":"user.created","data":{"id":1}}';

            // Act
            $signature = $this->signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toBeString()
                ->toStartWith('v1a,')
                ->toContain(',');
        });

        it('generates signature that can be verified with corresponding public key', function (): void {
            // Arrange
            $webhookId = 'msg_verify123';
            $timestamp = 1_234_567_890;
            $payload = '{"test":"data"}';

            // Act
            $signature = $this->signer->sign($webhookId, $timestamp, $payload);

            // Assert - Extract base64 signature and verify
            $parts = explode(',', $signature, 2);
            $decodedSignature = base64_decode($parts[1], true);
            $decodedPublicKey = base64_decode($this->publicKey, true);
            $signedContent = sprintf('%s.%d.%s', $webhookId, $timestamp, $payload);

            $isValid = sodium_crypto_sign_verify_detached(
                $decodedSignature,
                $signedContent,
                $decodedPublicKey,
            );

            expect($isValid)->toBeTrue();
        });

        it('includes webhook ID, timestamp, and payload in signed content', function (): void {
            // Arrange
            $webhookId = 'msg_content123';
            $timestamp = 9_876_543_210;
            $payload = '{"field":"value"}';

            // Act
            $signature = $this->signer->sign($webhookId, $timestamp, $payload);

            // Assert - Verify signature with exact content format
            $parts = explode(',', $signature, 2);
            $decodedSignature = base64_decode($parts[1], true);
            $decodedPublicKey = base64_decode($this->publicKey, true);
            $signedContent = sprintf('%s.%d.%s', $webhookId, $timestamp, $payload);

            $isValid = sodium_crypto_sign_verify_detached(
                $decodedSignature,
                $signedContent,
                $decodedPublicKey,
            );

            expect($isValid)->toBeTrue();
        });

        it('returns V1A_ED25519 signature version', function (): void {
            // Arrange
            // (signer created in beforeEach)

            // Act
            $version = $this->signer->version();

            // Assert
            expect($version)->toBe(SignatureVersion::V1A_ED25519)
                ->and($version->value)->toBe('v1a');
        });

        it('produces different signatures for different payloads with same key', function (): void {
            // Arrange
            $webhookId = 'msg_diff123';
            $timestamp = 1_234_567_890;
            $payload1 = '{"event":"user.created"}';
            $payload2 = '{"event":"user.deleted"}';

            // Act
            $signature1 = $this->signer->sign($webhookId, $timestamp, $payload1);
            $signature2 = $this->signer->sign($webhookId, $timestamp, $payload2);

            // Assert
            expect($signature1)->not->toBe($signature2);
        });

        it('produces different signatures for different timestamps', function (): void {
            // Arrange
            $webhookId = 'msg_timestamp123';
            $timestamp1 = 1_234_567_890;
            $timestamp2 = 9_876_543_210;
            $payload = '{"event":"test"}';

            // Act
            $signature1 = $this->signer->sign($webhookId, $timestamp1, $payload);
            $signature2 = $this->signer->sign($webhookId, $timestamp2, $payload);

            // Assert
            expect($signature1)->not->toBe($signature2);
        });
    });

    describe('Sad Paths', function (): void {
        it('throws exception for invalid base64 private key', function (): void {
            // Arrange
            $invalidKey = 'not-valid-base64!!!';
            $signer = new Ed25519Signer($invalidKey);

            // Act & Assert
            $signer->sign('msg_test', 1_234_567_890, '{}');
        })->throws(InvalidEd25519PrivateKeyException::class, 'Invalid Ed25519 private key format');

        it('throws exception for empty private key', function (): void {
            // Arrange
            $signer = new Ed25519Signer('');

            // Act & Assert
            $signer->sign('msg_test', 1_234_567_890, '{}');
        })->throws(SodiumException::class);

        it('throws exception for base64 string with invalid characters', function (): void {
            // Arrange
            $invalidKey = 'ABC@#$%^&*()';
            $signer = new Ed25519Signer($invalidKey);

            // Act & Assert
            $signer->sign('msg_test', 1_234_567_890, '{}');
        })->throws(InvalidEd25519PrivateKeyException::class, 'Invalid Ed25519 private key format');

        it('throws exception for whitespace-only private key', function (): void {
            // Arrange
            $signer = new Ed25519Signer('   ');

            // Act & Assert
            $signer->sign('msg_test', 1_234_567_890, '{}');
        })->throws(SodiumException::class);
    });

    describe('Edge Cases', function (): void {
        it('handles unicode characters in payload', function (): void {
            // Arrange
            $webhookId = 'msg_unicode123';
            $timestamp = 1_234_567_890;
            $payload = '{"message":"Hello 👋 World 🌍","emoji":"🚀"}';

            // Act
            $signature = $this->signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toBeString()
                ->toStartWith('v1a,');

            // Verify signature is valid
            $parts = explode(',', $signature, 2);
            $decodedSignature = base64_decode($parts[1], true);
            $decodedPublicKey = base64_decode($this->publicKey, true);
            $signedContent = sprintf('%s.%d.%s', $webhookId, $timestamp, $payload);

            $isValid = sodium_crypto_sign_verify_detached(
                $decodedSignature,
                $signedContent,
                $decodedPublicKey,
            );

            expect($isValid)->toBeTrue();
        });

        it('handles empty payload', function (): void {
            // Arrange
            $webhookId = 'msg_empty123';
            $timestamp = 1_234_567_890;
            $payload = '';

            // Act
            $signature = $this->signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toBeString()
                ->toStartWith('v1a,');
        });

        it('handles very long payload', function (): void {
            // Arrange
            $webhookId = 'msg_long123';
            $timestamp = 1_234_567_890;
            $payload = str_repeat('{"data":"value"},', 1_000);

            // Act
            $signature = $this->signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toBeString()
                ->toStartWith('v1a,');
        });

        it('handles special characters in webhook ID', function (): void {
            // Arrange
            $webhookId = 'msg_550e8400-e29b-41d4-a716-446655440000';
            $timestamp = 1_234_567_890;
            $payload = '{"test":"data"}';

            // Act
            $signature = $this->signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toBeString()
                ->toStartWith('v1a,');
        });

        it('handles zero timestamp', function (): void {
            // Arrange
            $webhookId = 'msg_zero123';
            $timestamp = 0;
            $payload = '{"test":"data"}';

            // Act
            $signature = $this->signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toBeString()
                ->toStartWith('v1a,');
        });

        it('handles very large timestamp', function (): void {
            // Arrange
            $webhookId = 'msg_large123';
            $timestamp = 2_147_483_647; // Max 32-bit int
            $payload = '{"test":"data"}';

            // Act
            $signature = $this->signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toBeString()
                ->toStartWith('v1a,');
        });

        it('handles payload with escaped characters', function (): void {
            // Arrange
            $webhookId = 'msg_escaped123';
            $timestamp = 1_234_567_890;
            $payload = '{"message":"Line1\\nLine2\\tTabbed","quote":"He said \\"hello\\""}';

            // Act
            $signature = $this->signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toBeString()
                ->toStartWith('v1a,');
        });
    });
});
