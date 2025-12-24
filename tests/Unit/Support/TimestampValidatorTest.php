<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Webhook\Exceptions\Client\ExpiredTimestampException;
use Cline\Webhook\Exceptions\Client\FutureTimestampException;
use Cline\Webhook\Support\TimestampValidator;
use Illuminate\Support\Facades\Date;

describe('TimestampValidator', function (): void {
    beforeEach(function (): void {
        // Set a fixed time for consistent testing
        Date::setTestNow('2024-01-15 12:00:00');
    });

    afterEach(function (): void {
        // Clean up test time
        Date::setTestNow();
    });

    describe('generate()', function (): void {
        test('generates current Unix timestamp', function (): void {
            // Arrange
            $expected = Date::now()->getTimestamp();

            // Act
            $result = TimestampValidator::generate();

            // Assert
            expect($result)->toBe($expected);
        });

        test('generates timestamp matching current time', function (): void {
            // Arrange
            Date::setTestNow('2024-06-01 14:30:45');
            $expected = 1_717_252_245; // Unix timestamp for 2024-06-01 14:30:45 UTC

            // Act
            $result = TimestampValidator::generate();

            // Assert
            expect($result)->toBe($expected);
        });
    });

    describe('isValid() - happy path', function (): void {
        test('accepts timestamp within default tolerance window', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $now = Date::now()->getTimestamp();
            $timestamp = $now - 150; // 2.5 minutes ago (within 5 minute default)

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeTrue();
        });

        test('accepts timestamp at current time', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $timestamp = Date::now()->getTimestamp();

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeTrue();
        });

        test('accepts timestamp with custom tolerance', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 600); // 10 minutes
            $now = Date::now()->getTimestamp();
            $timestamp = $now - 550; // 9 minutes 10 seconds ago

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeTrue();
        });

        test('accepts timestamp at exact tolerance boundary (inclusive)', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 300);
            $now = Date::now()->getTimestamp();
            $timestamp = $now - 300; // Exactly 5 minutes ago

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('isValid() - expired timestamps', function (): void {
        test('rejects timestamp beyond default tolerance', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $now = Date::now()->getTimestamp();
            $timestamp = $now - 301; // 5 minutes 1 second ago

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeFalse();
        });

        test('rejects very old timestamp', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $now = Date::now()->getTimestamp();
            $timestamp = $now - 86_400; // 24 hours ago

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeFalse();
        });

        test('rejects timestamp just beyond custom tolerance', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 60);
            $now = Date::now()->getTimestamp();
            $timestamp = $now - 61; // 1 minute 1 second ago

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('isValid() - future timestamps', function (): void {
        test('rejects timestamp in the future', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $now = Date::now()->getTimestamp();
            $timestamp = $now + 1; // 1 second in future

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeFalse();
        });

        test('rejects timestamp far in the future', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $now = Date::now()->getTimestamp();
            $timestamp = $now + 3_600; // 1 hour in future

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('validate() - exceptions', function (): void {
        test('throws ExpiredTimestampException for old timestamp', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $now = Date::now()->getTimestamp();
            $timestamp = $now - 400; // 6 minutes 40 seconds ago

            // Act & Assert
            expect(fn () => $validator->validate($timestamp))
                ->toThrow(ExpiredTimestampException::class);
        });

        test('throws ExpiredTimestampException with correct message', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 300);
            $now = Date::now()->getTimestamp();
            $timestamp = $now - 500;

            // Act & Assert
            try {
                $validator->validate($timestamp);
                expect(true)->toBeFalse('Exception should have been thrown');
            } catch (ExpiredTimestampException $e) {
                expect($e->getMessage())
                    ->toContain("Webhook timestamp ({$timestamp}) is too old")
                    ->toContain('Age: 500s')
                    ->toContain('Tolerance: 300s');
            }
        });

        test('throws FutureTimestampException for future timestamp', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $now = Date::now()->getTimestamp();
            $timestamp = $now + 100; // 100 seconds in future

            // Act & Assert
            expect(fn () => $validator->validate($timestamp))
                ->toThrow(FutureTimestampException::class);
        });

        test('throws FutureTimestampException with correct message', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $now = Date::now()->getTimestamp();
            $timestamp = $now + 50;

            // Act & Assert
            try {
                $validator->validate($timestamp);
                expect(true)->toBeFalse('Exception should have been thrown');
            } catch (FutureTimestampException $e) {
                expect($e->getMessage())
                    ->toContain("Webhook timestamp ({$timestamp}) is in the future")
                    ->toContain("current: {$now}");
            }
        });

        test('does not throw exception for valid timestamp', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $now = Date::now()->getTimestamp();
            $timestamp = $now - 100; // 100 seconds ago

            // Act & Assert
            expect(fn () => $validator->validate($timestamp))->not->toThrow(Exception::class);
        });
    });

    describe('tolerance configuration', function (): void {
        test('uses default 5 minute tolerance', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $now = Date::now()->getTimestamp();

            // Act & Assert
            expect($validator->isValid($now - 299))->toBeTrue();
            expect($validator->isValid($now - 300))->toBeTrue();
            expect($validator->isValid($now - 301))->toBeFalse();
        });

        test('accepts custom tolerance in constructor', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 120); // 2 minutes
            $now = Date::now()->getTimestamp();

            // Act & Assert
            expect($validator->isValid($now - 119))->toBeTrue();
            expect($validator->isValid($now - 120))->toBeTrue();
            expect($validator->isValid($now - 121))->toBeFalse();
        });

        test('works with very small tolerance', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 1);
            $now = Date::now()->getTimestamp();

            // Act & Assert
            expect($validator->isValid($now))->toBeTrue();
            expect($validator->isValid($now - 1))->toBeTrue();
            expect($validator->isValid($now - 2))->toBeFalse();
        });

        test('works with very large tolerance', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 86_400); // 24 hours
            $now = Date::now()->getTimestamp();

            // Act & Assert
            expect($validator->isValid($now - 86_399))->toBeTrue();
            expect($validator->isValid($now - 86_400))->toBeTrue();
            expect($validator->isValid($now - 86_401))->toBeFalse();
        });

        test('works with zero tolerance', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 0);
            $now = Date::now()->getTimestamp();

            // Act & Assert
            expect($validator->isValid($now))->toBeTrue();
            expect($validator->isValid($now - 1))->toBeFalse();
        });
    });

    describe('edge cases', function (): void {
        test('handles zero timestamp', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $timestamp = 0;

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles negative timestamp', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $timestamp = -1_000;

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles very large timestamp (year 2100)', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $timestamp = 4_102_444_800; // 2100-01-01 00:00:00 UTC
            $now = Date::now()->getTimestamp();

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles timestamp at Unix epoch', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $timestamp = 1; // 1970-01-01 00:00:01 UTC

            // Act
            $result = $validator->isValid($timestamp);

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles boundary at one second over tolerance', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 100);
            $now = Date::now()->getTimestamp();
            $timestamp = $now - 101;

            // Act & Assert
            expect($validator->isValid($timestamp))->toBeFalse();
            expect(fn () => $validator->validate($timestamp))
                ->toThrow(ExpiredTimestampException::class);
        });

        test('handles boundary at exact tolerance limit', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 100);
            $now = Date::now()->getTimestamp();
            $timestamp = $now - 100;

            // Act & Assert
            expect($validator->isValid($timestamp))->toBeTrue();
            expect(fn () => $validator->validate($timestamp))->not->toThrow(Exception::class);
        });

        test('handles multiple validations with same validator instance', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 200);
            $now = Date::now()->getTimestamp();

            // Act & Assert
            expect($validator->isValid($now))->toBeTrue();
            expect($validator->isValid($now - 100))->toBeTrue();
            expect($validator->isValid($now - 200))->toBeTrue();
            expect($validator->isValid($now - 201))->toBeFalse();
            expect($validator->isValid($now + 1))->toBeFalse();
        });
    });

    describe('precision and accuracy', function (): void {
        test('validates timestamps with second precision', function (): void {
            // Arrange
            Date::setTestNow('2024-01-15 12:00:00.999999'); // Microseconds should not affect result
            $validator = new TimestampValidator(toleranceSeconds: 10);
            $now = Date::now()->getTimestamp();

            // Act & Assert
            expect($validator->isValid($now))->toBeTrue();
            expect($validator->isValid($now - 10))->toBeTrue();
            expect($validator->isValid($now - 11))->toBeFalse();
        });

        test('consistent results for same timestamp', function (): void {
            // Arrange
            $validator = new TimestampValidator();
            $timestamp = Date::now()->getTimestamp() - 100;

            // Act
            $result1 = $validator->isValid($timestamp);
            $result2 = $validator->isValid($timestamp);
            $result3 = $validator->isValid($timestamp);

            // Assert
            expect($result1)->toBe($result2);
            expect($result2)->toBe($result3);
            expect($result1)->toBeTrue();
        });
    });

    describe('integration scenarios', function (): void {
        test('validates webhook replay attack prevention scenario', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 300);

            // Simulate webhook received
            $webhookTimestamp = TimestampValidator::generate();

            // Act - Immediate validation should pass
            expect($validator->isValid($webhookTimestamp))->toBeTrue();

            // Simulate time passing - replay attack attempt
            Date::setTestNow(Date::now()->addMinutes(6));

            // Assert - Same timestamp should now be rejected
            expect($validator->isValid($webhookTimestamp))->toBeFalse();
        });

        test('validates clock skew protection scenario', function (): void {
            // Arrange
            $validator = new TimestampValidator(toleranceSeconds: 300);

            // Simulate webhook from server with slightly fast clock (1 second ahead)
            $futureTimestamp = TimestampValidator::generate() + 1;

            // Act & Assert - Future timestamps rejected to prevent clock skew attacks
            expect($validator->isValid($futureTimestamp))->toBeFalse();
            expect(fn () => $validator->validate($futureTimestamp))
                ->toThrow(FutureTimestampException::class);
        });
    });
});
