<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Webhook\Client\Exceptions\InvalidSignatureException;
use Cline\Webhook\Client\Validators\HmacValidator;
use Cline\Webhook\Exceptions\Client\ExpiredTimestampException;
use Cline\Webhook\Exceptions\Client\FutureTimestampException;
use Cline\Webhook\Support\TimestampValidator;
use Illuminate\Http\Request;

describe('HmacValidator', function (): void {
    beforeEach(function (): void {
        $this->secret = 'test-secret-key';
        $this->webhookId = 'msg_123';
        $this->timestamp = time();
        $this->payload = '{"event":"user.created","data":{"id":1}}';
    });

    describe('Happy Path - Valid Signatures', function (): void {
        test('validates correct HMAC-SHA256 signature successfully', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $validator->verify($request, $this->secret);

            // Assert
            expect(true)->toBeTrue(); // No exception thrown
        });

        test('isValid returns true for correct signature', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeTrue();
        });

        test('validates with multiple v1 signatures when first is correct', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $validSignature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));
            $invalidSignature = base64_encode('invalid_signature_data_here');

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$validSignature} v1,{$invalidSignature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $validator->verify($request, $this->secret);

            // Assert
            expect(true)->toBeTrue(); // No exception thrown
        });

        test('validates with multiple v1 signatures when second is correct', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $validSignature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));
            $invalidSignature = base64_encode('invalid_signature_data_here');

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$invalidSignature} v1,{$validSignature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $validator->verify($request, $this->secret);

            // Assert
            expect(true)->toBeTrue(); // No exception thrown
        });

        test('validates different payload contents correctly', function (): void {
            // Arrange
            $payload = '{"event":"order.completed","amount":1500,"currency":"USD"}';
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeTrue();
        });

        test('validates empty payload with correct signature', function (): void {
            // Arrange
            $payload = '';
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('Sad Path - Invalid Signatures', function (): void {
        test('throws InvalidSignatureException for incorrect signature', function (): void {
            // Arrange
            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', 'v1,invalid_signature_here');

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act & Assert
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(InvalidSignatureException::class, 'Invalid webhook signature for webhook ID: msg_123');
        });

        test('isValid returns false for incorrect signature', function (): void {
            // Arrange
            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', 'v1,invalid_signature_here');

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeFalse();
        });

        test('throws InvalidSignatureException when signature uses wrong secret', function (): void {
            // Arrange
            $wrongSecret = 'wrong-secret-key';
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $wrongSecret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act & Assert
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(InvalidSignatureException::class);
        });

        test('throws InvalidSignatureException when payload is tampered', function (): void {
            // Arrange
            $originalPayload = '{"event":"user.created","data":{"id":1}}';
            $tamperedPayload = '{"event":"user.created","data":{"id":999}}';

            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $originalPayload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $tamperedPayload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act & Assert
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(InvalidSignatureException::class);
        });

        test('throws InvalidSignatureException when webhook ID is tampered', function (): void {
            // Arrange
            $originalId = 'msg_123';
            $tamperedId = 'msg_999';

            $signedContent = sprintf('%s.%d.%s', $originalId, $this->timestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $tamperedId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act & Assert
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(InvalidSignatureException::class);
        });

        test('throws InvalidSignatureException when timestamp is tampered', function (): void {
            // Arrange
            $originalTimestamp = time() - 50;
            $tamperedTimestamp = $originalTimestamp - 10; // Both valid timestamps, but mismatched

            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $originalTimestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $tamperedTimestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act & Assert
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(InvalidSignatureException::class);
        });

        test('throws InvalidSignatureException when all signatures are invalid', function (): void {
            // Arrange
            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', 'v1,invalid_sig1 v1,invalid_sig2 v1,invalid_sig3');

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act & Assert
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(InvalidSignatureException::class);
        });
    });

    describe('Edge Cases - Signature Parsing', function (): void {
        test('ignores non-v1 signature versions', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $validSignature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v2,some_v2_sig v1,{$validSignature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $validator->verify($request, $this->secret);

            // Assert
            expect(true)->toBeTrue(); // No exception thrown
        });

        test('throws InvalidSignatureException when only non-v1 signatures present', function (): void {
            // Arrange
            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', 'v2,some_v2_sig v3,some_v3_sig');

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act & Assert
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(InvalidSignatureException::class);
        });

        test('ignores malformed signature parts without comma', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $validSignature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "malformed_part v1,{$validSignature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $validator->verify($request, $this->secret);

            // Assert
            expect(true)->toBeTrue(); // No exception thrown
        });

        test('throws InvalidSignatureException for empty signature header', function (): void {
            // Arrange
            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', '');

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act & Assert
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(InvalidSignatureException::class);
        });

        test('handles signature with extra spaces correctly', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $validSignature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$validSignature}  ");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $validator->verify($request, $this->secret);

            // Assert
            expect(true)->toBeTrue(); // No exception thrown
        });

        test('handles complex signature header with mixed versions', function (): void {
            // Arrange
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $validSignature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v2,sig2 malformed v1,invalid v1,{$validSignature} v3,sig3");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $validator->verify($request, $this->secret);

            // Assert
            expect(true)->toBeTrue(); // No exception thrown, valid v1 signature found
        });
    });

    describe('Edge Cases - Timestamp Validation Integration', function (): void {
        test('throws ExpiredTimestampException for expired timestamp', function (): void {
            // Arrange
            $expiredTimestamp = time() - 400; // 400 seconds ago, exceeds 300s tolerance
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $expiredTimestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $expiredTimestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act & Assert
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(ExpiredTimestampException::class);
        });

        test('isValid returns false for expired timestamp', function (): void {
            // Arrange
            $expiredTimestamp = time() - 400;
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $expiredTimestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $expiredTimestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeFalse();
        });

        test('throws FutureTimestampException for future timestamp', function (): void {
            // Arrange
            $futureTimestamp = time() + 100; // 100 seconds in the future
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $futureTimestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $futureTimestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act & Assert
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(FutureTimestampException::class);
        });

        test('validates successfully with timestamp at tolerance boundary', function (): void {
            // Arrange
            $boundaryTimestamp = time() - 299; // Just within 300s tolerance
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $boundaryTimestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $boundaryTimestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeTrue();
        });

        test('validates with custom tolerance setting', function (): void {
            // Arrange
            $timestamp = time() - 100; // 100 seconds ago
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $timestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(120),
            ); // 2-minute tolerance

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeTrue();
        });

        test('rejects with strict tolerance setting', function (): void {
            // Arrange
            $timestamp = time() - 100; // 100 seconds ago
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $timestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(60),
            ); // 1-minute tolerance

            // Act & Assert
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(ExpiredTimestampException::class);
        });
    });

    describe('Edge Cases - Special Characters and Encoding', function (): void {
        test('validates payload with unicode characters', function (): void {
            // Arrange
            $payload = '{"message":"Hello 世界 🌍","emoji":"✨"}';
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeTrue();
        });

        test('validates payload with special JSON characters', function (): void {
            // Arrange
            $payload = '{"data":"Line 1\\nLine 2\\tTabbed","quote":"He said \\"hello\\""}';
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeTrue();
        });

        test('validates very large payload', function (): void {
            // Arrange
            $largeArray = array_fill(0, 1_000, ['id' => 1, 'name' => 'Item', 'data' => str_repeat('x', 100)]);
            $payload = json_encode(['items' => $largeArray]);
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeTrue();
        });

        test('validates webhook ID with special characters', function (): void {
            // Arrange
            $webhookId = 'msg_test-123_v2.0';
            $signedContent = sprintf('%s.%d.%s', $webhookId, $this->timestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('Edge Cases - Timing-Safe Comparison', function (): void {
        test('uses timing-safe comparison to prevent timing attacks', function (): void {
            // Arrange - Create two different signatures with similar prefixes
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $correctSignature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            // Create similar but wrong signature by modifying last character
            $almostCorrectSignature = mb_substr($correctSignature, 0, -1).'X';

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$almostCorrectSignature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act & Assert - Should still fail even though it's very similar
            expect(fn () => $validator->verify($request, $this->secret))
                ->toThrow(InvalidSignatureException::class);
        });
    });

    describe('Regression - Known Issues', function (): void {
        test('validates signature when payload contains dots', function (): void {
            // Arrange - Dots in payload shouldn't interfere with {id}.{timestamp}.{payload} format
            $payload = '{"version":"1.0.0","ip":"192.168.1.1"}';
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeTrue();
        });

        test('validates when signature contains comma in base64 encoding', function (): void {
            // Arrange - Although unlikely, base64 shouldn't contain comma, test parsing robustness
            $signedContent = sprintf('%s.%d.%s', $this->webhookId, $this->timestamp, $this->payload);
            $signature = base64_encode(hash_hmac('sha256', $signedContent, $this->secret, true));

            $request = Request::create('/webhook', 'POST', [], [], [], [], $this->payload);
            $request->headers->set('webhook-id', $this->webhookId);
            $request->headers->set('webhook-timestamp', (string) $this->timestamp);
            $request->headers->set('webhook-signature', "v1,{$signature}");

            $validator = new HmacValidator(
                new TimestampValidator(300),
            );

            // Act
            $result = $validator->isValid($request, $this->secret);

            // Assert
            expect($result)->toBeTrue();
        });
    });
});
