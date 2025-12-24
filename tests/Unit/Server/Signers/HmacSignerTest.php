<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Webhook\Enums\SignatureVersion;
use Cline\Webhook\Server\Signers\HmacSigner;

describe('HmacSigner', function (): void {
    describe('Happy Path', function (): void {
        test('generates signature with correct format v1,base64', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '{"event":"user.created"}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
            expect($signature)->toMatch('/^v1,[A-Za-z0-9+\/]+=*$/');
        });

        test('produces consistent signature for same input', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '{"event":"user.created"}';

            // Act
            $signature1 = $signer->sign($webhookId, $timestamp, $payload);
            $signature2 = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature1)->toBe($signature2);
        });

        test('signs payload with webhook id and timestamp included', function (): void {
            // Arrange
            $secret = 'whsec_C2FVsBQIhrscChlQIMV+b5sSYspob7oD';
            $signer = new HmacSigner($secret);
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '{"event":"user.created","data":{"id":123}}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            // Verify it's not just signing the payload alone
            $payloadOnlySignature = 'v1,'.base64_encode(hash_hmac('sha256', $payload, $secret, true));
            expect($signature)->not->toBe($payloadOnlySignature);

            // Verify the signed content format: webhookId.timestamp.payload
            $expectedContent = sprintf('%s.%d.%s', $webhookId, $timestamp, $payload);
            $expectedSignature = 'v1,'.base64_encode(hash_hmac('sha256', $expectedContent, $secret, true));
            expect($signature)->toBe($expectedSignature);
        });

        test('returns v1_hmac as signature version', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');

            // Act
            $version = $signer->version();

            // Assert
            expect($version)->toBe(SignatureVersion::V1_HMAC);
        });
    });

    describe('Different Secrets', function (): void {
        test('produces different signatures with different secrets', function (): void {
            // Arrange
            $signer1 = new HmacSigner('secret-one');
            $signer2 = new HmacSigner('secret-two');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '{"event":"user.created"}';

            // Act
            $signature1 = $signer1->sign($webhookId, $timestamp, $payload);
            $signature2 = $signer2->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature1)->not->toBe($signature2);
        });

        test('handles standard webhooks secret format', function (): void {
            // Arrange
            $secret = 'whsec_C2FVsBQIhrscChlQIMV+b5sSYspob7oD';
            $signer = new HmacSigner($secret);
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '{"event":"user.created"}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
            expect($signature)->toMatch('/^v1,[A-Za-z0-9+\/]+=*$/');
        });
    });

    describe('Edge Cases - Payloads', function (): void {
        test('handles empty payload', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
            expect($signature)->toMatch('/^v1,[A-Za-z0-9+\/]+=*$/');
        });

        test('handles payload with special characters', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '{"message":"Hello! @#$%^&*()_+-={}[]|\\:\";<>?,./"}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
            expect($signature)->toMatch('/^v1,[A-Za-z0-9+\/]+=*$/');
        });

        test('handles payload with unicode characters', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '{"message":"Hello 世界 🌍 café"}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
            expect($signature)->toMatch('/^v1,[A-Za-z0-9+\/]+=*$/');
        });

        test('handles large payload', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $largeData = str_repeat('{"key":"value"},', 1_000);
            $payload = '{"items":['.mb_rtrim($largeData, ',').']}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
            expect($signature)->toMatch('/^v1,[A-Za-z0-9+\/]+=*$/');
        });

        test('handles payload with newlines and whitespace', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = "{\n  \"event\": \"user.created\",\n  \"data\": {\n    \"id\": 123\n  }\n}";

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
            expect($signature)->toMatch('/^v1,[A-Za-z0-9+\/]+=*$/');
        });
    });

    describe('Edge Cases - Webhook IDs', function (): void {
        test('handles empty webhook id', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = '';
            $timestamp = 1_614_240_000;
            $payload = '{"event":"user.created"}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
        });

        test('handles webhook id with special characters', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_test-id_123/456';
            $timestamp = 1_614_240_000;
            $payload = '{"event":"user.created"}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
            expect($signature)->toMatch('/^v1,[A-Za-z0-9+\/]+=*$/');
        });
    });

    describe('Edge Cases - Timestamps', function (): void {
        test('handles zero timestamp', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 0;
            $payload = '{"event":"user.created"}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
        });

        test('handles future timestamp', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 2_147_483_647; // Max 32-bit timestamp
            $payload = '{"event":"user.created"}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
        });

        test('produces different signatures for different timestamps', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $payload = '{"event":"user.created"}';

            // Act
            $signature1 = $signer->sign($webhookId, 1_614_240_000, $payload);
            $signature2 = $signer->sign($webhookId, 1_614_240_001, $payload);

            // Assert
            expect($signature1)->not->toBe($signature2);
        });
    });

    describe('Standard Webhooks Spec Compliance', function (): void {
        test('signature format matches standard webhooks v1 specification', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '{"event":"user.created"}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            // Format: v1,<base64-encoded-signature>
            [$version, $encodedSignature] = explode(',', $signature, 2);
            expect($version)->toBe('v1');
            expect($encodedSignature)->toMatch('/^[A-Za-z0-9+\/]+=*$/');

            // Verify base64 decoding works
            $decoded = base64_decode($encodedSignature, true);
            expect($decoded)->not->toBeFalse();
            expect($decoded)->toHaveLength(32); // SHA256 produces 32 bytes
        });

        test('uses hmac sha256 algorithm', function (): void {
            // Arrange
            $secret = 'test-secret';
            $signer = new HmacSigner($secret);
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '{"event":"user.created"}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            // Manually compute expected signature
            $signedContent = sprintf('%s.%d.%s', $webhookId, $timestamp, $payload);
            $expectedHash = hash_hmac('sha256', $signedContent, $secret, true);
            $expectedSignature = 'v1,'.base64_encode($expectedHash);

            expect($signature)->toBe($expectedSignature);
        });

        test('signed content format is webhookId.timestamp.payload', function (): void {
            // Arrange
            $secret = 'test-secret';
            $signer = new HmacSigner($secret);
            $webhookId = 'msg_abc123';
            $timestamp = 1_234_567_890;
            $payload = '{"test":true}';

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            // The signed content should be: msg_abc123.1234567890.{"test":true}
            $expectedContent = 'msg_abc123.1234567890.{"test":true}';
            $expectedHash = hash_hmac('sha256', $expectedContent, $secret, true);
            $expectedSignature = 'v1,'.base64_encode($expectedHash);

            expect($signature)->toBe($expectedSignature);
        });
    });

    describe('Immutability', function (): void {
        test('signer is immutable - secret cannot be changed', function (): void {
            // Arrange
            $signer = new HmacSigner('original-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '{"event":"user.created"}';

            // Act
            $signature1 = $signer->sign($webhookId, $timestamp, $payload);
            // Can't modify secret due to readonly
            $signature2 = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature1)->toBe($signature2);
        });

        test('multiple signers with same secret produce same signatures', function (): void {
            // Arrange
            $secret = 'shared-secret';
            $signer1 = new HmacSigner($secret);
            $signer2 = new HmacSigner($secret);
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = '{"event":"user.created"}';

            // Act
            $signature1 = $signer1->sign($webhookId, $timestamp, $payload);
            $signature2 = $signer2->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature1)->toBe($signature2);
        });
    });

    describe('Real-world Scenarios', function (): void {
        test('handles typical webhook payload structure', function (): void {
            // Arrange
            $signer = new HmacSigner('whsec_C2FVsBQIhrscChlQIMV+b5sSYspob7oD');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = json_encode([
                'event' => 'invoice.payment_succeeded',
                'data' => [
                    'id' => 'inv_1234567890',
                    'customer' => 'cus_9876543210',
                    'amount' => 5_000,
                    'currency' => 'usd',
                ],
            ]);

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
            expect($signature)->toMatch('/^v1,[A-Za-z0-9+\/]+=*$/');
        });

        test('handles payload with nested objects and arrays', function (): void {
            // Arrange
            $signer = new HmacSigner('test-secret');
            $webhookId = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
            $timestamp = 1_614_240_000;
            $payload = json_encode([
                'event' => 'order.created',
                'data' => [
                    'id' => 'ord_123',
                    'items' => [
                        ['sku' => 'ITEM-1', 'quantity' => 2],
                        ['sku' => 'ITEM-2', 'quantity' => 1],
                    ],
                    'customer' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ],
                ],
            ]);

            // Act
            $signature = $signer->sign($webhookId, $timestamp, $payload);

            // Assert
            expect($signature)->toStartWith('v1,');
            expect($signature)->toMatch('/^v1,[A-Za-z0-9+\/]+=*$/');
        });
    });
});
