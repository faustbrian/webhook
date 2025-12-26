<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Client\Validators;

use Cline\Webhook\Client\Exceptions\InvalidSignatureException;
use Cline\Webhook\Client\Validators\Ed25519Validator;
use Cline\Webhook\Exceptions\Client\ExpiredTimestampException;
use Cline\Webhook\Exceptions\Client\FutureTimestampException;
use Cline\Webhook\Support\TimestampValidator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

use function base64_encode;
use function beforeEach;
use function describe;
use function expect;
use function sodium_crypto_sign_detached;
use function sodium_crypto_sign_keypair;
use function sodium_crypto_sign_publickey;
use function sodium_crypto_sign_secretkey;
use function sprintf;
use function str_repeat;
use function test;

/**
 * Comprehensive tests for Ed25519 signature validator.
 *
 * Tests cover:
 * - Valid signature verification (happy path)
 * - Invalid signature rejection (sad path)
 * - Signature header parsing
 * - Timestamp validation integration
 * - Base64 decoding edge cases
 * - Multiple signature versions
 * - Error handling
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
describe('Ed25519Validator', function (): void {
    beforeEach(function (): void {
        // Generate a fresh Ed25519 keypair for each test
        $keypair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);
        $this->publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));

        // Test data
        $this->webhookId = 'msg_123';
        $this->timestamp = 1_234_567_890;
        $this->payload = '{"event":"user.created","user_id":123}';

        // Mock Date facade to return a time that makes timestamp valid
        Date::shouldReceive('now->getTimestamp')
            ->andReturn($this->timestamp);

        // Create validator with large tolerance (effectively disabling timestamp checks for most tests)
        $this->timestampValidator = new TimestampValidator(999_999);
        $this->validator = new Ed25519Validator(
            $this->publicKey,
            $this->timestampValidator,
        );
    });

    describe('Happy Paths', function (): void {
        test('verifies valid Ed25519 signature successfully', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))->not->toThrow(Exception::class);
        })->group('happy-path');

        test('isValid returns true for correct Ed25519 signature', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act
            $result = $this->validator->isValid($request, 'secret');

            // Assert
            expect($result)->toBeTrue();
        })->group('happy-path');

        test('accepts multiple v1a signatures and validates first valid one', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            // Include an invalid signature first, then the valid one
            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,invalid_sig_here v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))->not->toThrow(Exception::class);
        })->group('happy-path');

        test('validates signature with different payload content', function (): void {
            // Arrange
            $customPayload = '{"event":"order.completed","order_id":999,"amount":1234.56}';
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $customPayload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $customPayload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))->not->toThrow(Exception::class);
        })->group('happy-path');
    });

    describe('Sad Paths', function (): void {
        test('isValid returns false for invalid signature', function (): void {
            // Arrange
            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,invalid_signature_base64_encoded==',
                ],
                $this->payload,
            );

            // Act
            $result = $this->validator->isValid($request, 'secret');

            // Assert
            expect($result)->toBeFalse();
        })->group('sad-path');

        test('throws InvalidSignatureException for tampered payload', function (): void {
            // Arrange - Sign original payload
            $originalPayload = '{"event":"user.created","user_id":123}';
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $originalPayload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            // Create request with tampered payload
            $tamperedPayload = '{"event":"user.created","user_id":999}';
            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $tamperedPayload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))
                ->toThrow(InvalidSignatureException::class, 'Invalid webhook signature for webhook ID: '.$this->webhookId);
        })->group('sad-path');

        test('throws InvalidSignatureException for wrong public key', function (): void {
            // Arrange - Create signature with one keypair
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            // Create validator with different public key
            $differentKeypair = sodium_crypto_sign_keypair();
            $differentPublicKey = base64_encode(sodium_crypto_sign_publickey($differentKeypair));

            $wrongValidator = new Ed25519Validator(
                $differentPublicKey,
                $this->timestampValidator,
            );

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $wrongValidator->verify($request, 'secret'))
                ->toThrow(InvalidSignatureException::class, 'Invalid webhook signature for webhook ID: '.$this->webhookId);
        })->group('sad-path');

        test('throws exception when timestamp validation fails with expired timestamp', function (): void {
            // Arrange
            $oldTimestamp = $this->timestamp - 400; // 400 seconds in the past
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $oldTimestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            // Create validator with strict tolerance (5 minutes = 300 seconds)
            $strictValidator = new Ed25519Validator(
                $this->publicKey,
                new TimestampValidator(300),
            );

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $oldTimestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $strictValidator->verify($request, 'secret'))
                ->toThrow(ExpiredTimestampException::class);
        })->group('sad-path');

        test('throws exception when timestamp validation fails with future timestamp', function (): void {
            // Arrange
            $futureTimestamp = $this->timestamp + 1_000; // 1000 seconds in the future
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $futureTimestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            // Create validator with standard tolerance
            $strictValidator = new Ed25519Validator(
                $this->publicKey,
                new TimestampValidator(300),
            );

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $futureTimestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $strictValidator->verify($request, 'secret'))
                ->toThrow(FutureTimestampException::class);
        })->group('sad-path');

        test('throws InvalidSignatureException when no v1a signatures present', function (): void {
            // Arrange - Use non-v1a version (v1 instead)
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$encodedSignature, // Wrong version
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))
                ->toThrow(InvalidSignatureException::class, 'Invalid webhook signature for webhook ID: '.$this->webhookId);
        })->group('sad-path');

        test('isValid returns false when timestamp validation fails', function (): void {
            // Arrange
            $oldTimestamp = $this->timestamp - 400; // 400 seconds in the past
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $oldTimestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            // Create validator with strict tolerance
            $strictValidator = new Ed25519Validator(
                $this->publicKey,
                new TimestampValidator(300),
            );

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $oldTimestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act
            $result = $strictValidator->isValid($request, 'secret');

            // Assert
            expect($result)->toBeFalse();
        })->group('sad-path');
    });

    describe('Edge Cases', function (): void {
        test('handles malformed base64 public key gracefully', function (): void {
            // Arrange
            $validator = new Ed25519Validator(
                'not-valid-base64!@#$',
                $this->timestampValidator,
            );

            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $validator->verify($request, 'secret'))
                ->toThrow(InvalidSignatureException::class);
        })->group('edge-case');

        test('handles malformed base64 signature gracefully', function (): void {
            // Arrange
            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,not-valid-base64!@#$%^&*()',
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))
                ->toThrow(InvalidSignatureException::class);
        })->group('edge-case');

        test('handles empty signature string', function (): void {
            // Arrange
            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,',
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))
                ->toThrow(InvalidSignatureException::class);
        })->group('edge-case');

        test('handles signature header without comma separator', function (): void {
            // Arrange
            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a_no_comma_separator',
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))
                ->toThrow(InvalidSignatureException::class);
        })->group('edge-case');

        test('handles empty payload', function (): void {
            // Arrange
            $emptyPayload = '';
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $emptyPayload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $emptyPayload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))->not->toThrow(Exception::class);
        })->group('edge-case');

        test('handles unicode characters in payload', function (): void {
            // Arrange
            $unicodePayload = '{"message":"Hello 世界 🌍 émojis"}';
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $unicodePayload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $unicodePayload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))->not->toThrow(Exception::class);
        })->group('edge-case');

        test('handles very large payload', function (): void {
            // Arrange
            $largePayload = '{"data":"'.str_repeat('x', 10_000).'"}';
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $largePayload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $largePayload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))->not->toThrow(Exception::class);
        })->group('edge-case');

        test('handles multiple spaces in signature header', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => '  v1a,'.$encodedSignature.'  v1,other_sig  ',
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))->not->toThrow(Exception::class);
        })->group('edge-case');

        test('validates signature with special characters in webhook ID', function (): void {
            // Arrange
            $specialWebhookId = 'msg_123-abc_xyz.test';
            $signedContent = sprintf('%s.%d.%s', $specialWebhookId, $this->timestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $specialWebhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))->not->toThrow(Exception::class);
        })->group('edge-case');
    });

    describe('Signature Parsing', function (): void {
        test('parses single v1a signature correctly', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))->not->toThrow(Exception::class);
        })->group('edge-case');

        test('parses multiple signatures with different versions', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            // Include v1 (HMAC) and v1a (Ed25519) signatures
            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1,hmac_signature_here v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act & Assert - Should find and verify v1a signature
            expect(fn () => $this->validator->verify($request, 'secret'))->not->toThrow(Exception::class);
        })->group('edge-case');

        test('ignores non-v1a signatures', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = sodium_crypto_sign_detached($signedContent, $this->secretKey);
            $encodedSignature = base64_encode($signature);

            // First two are v1 (should be ignored), last one is v1a (should be verified)
            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                [
                    'HTTP_WEBHOOK_ID' => $this->webhookId,
                    'HTTP_WEBHOOK_TIMESTAMP' => (string) $this->timestamp,
                    'HTTP_WEBHOOK_SIGNATURE' => 'v1,sig1 v1,sig2 v1a,'.$encodedSignature,
                ],
                $this->payload,
            );

            // Act & Assert
            expect(fn () => $this->validator->verify($request, 'secret'))->not->toThrow(Exception::class);
        })->group('edge-case');
    });
});
