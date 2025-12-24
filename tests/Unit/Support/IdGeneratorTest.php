<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Webhook\Support\IdGenerator;
use Illuminate\Support\Str;

describe('IdGenerator', function (): void {
    describe('Happy Path', function (): void {
        test('generate creates a valid ULID', function (): void {
            // Arrange & Act
            $id = IdGenerator::generate();

            // Assert
            expect($id)->toBeString()
                ->and(Str::isUlid($id))->toBeTrue()
                ->and($id)->toHaveLength(26);
        });

        test('generate creates unique IDs on successive calls', function (): void {
            // Arrange
            $iterations = 1_000;
            $ids = [];

            // Act
            for ($i = 0; $i < $iterations; ++$i) {
                $ids[] = IdGenerator::generate();
            }

            // Assert
            $uniqueIds = array_unique($ids);
            expect($uniqueIds)->toHaveCount($iterations);
        });

        test('generated ULIDs are lexicographically sortable', function (): void {
            // Arrange
            $ids = [];

            // Act
            for ($i = 0; $i < 10; ++$i) {
                $ids[] = IdGenerator::generate();
                usleep(1_000); // 1ms delay to ensure timestamp difference
            }

            // Assert
            $sortedIds = $ids;
            sort($sortedIds);
            expect($ids)->toBe($sortedIds);
        });

        test('isValid returns true for valid ULID', function (): void {
            // Arrange
            $validUlid = IdGenerator::generate();

            // Act
            $result = IdGenerator::isValid($validUlid);

            // Assert
            expect($result)->toBeTrue();
        });

        test('isValid returns true for manually created valid ULID', function (): void {
            // Arrange - Use Str::ulid() to generate a valid ULID for testing
            $validUlid = (string) Str::ulid();

            // Act
            $result = IdGenerator::isValid($validUlid);

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('Sad Path', function (): void {
        test('isValid returns false for UUID format', function (): void {
            // Arrange
            $uuid = '550e8400-e29b-41d4-a716-446655440000';

            // Act
            $result = IdGenerator::isValid($uuid);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isValid returns false for numeric ID', function (): void {
            // Arrange
            $numericId = '12345';

            // Act
            $result = IdGenerator::isValid($numericId);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isValid returns false for invalid ULID with invalid characters', function (): void {
            // Arrange
            $invalidUlid = '01HQRZ9K3X@#$%^&*()!~`+={}';

            // Act
            $result = IdGenerator::isValid($invalidUlid);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isValid returns false for ULID with incorrect length', function (): void {
            // Arrange
            $shortUlid = '01HQRZ9K3X';

            // Act
            $result = IdGenerator::isValid($shortUlid);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isValid returns false for empty string', function (): void {
            // Arrange
            $emptyString = '';

            // Act
            $result = IdGenerator::isValid($emptyString);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('generated ULIDs use uppercase characters', function (): void {
            // Arrange
            $iterations = 100;

            // Act
            for ($i = 0; $i < $iterations; ++$i) {
                $id = IdGenerator::generate();

                // Assert
                expect($id)->toBe(mb_strtoupper($id));
            }
        });

        test('generated ULIDs are alphanumeric only', function (): void {
            // Arrange
            $iterations = 100;

            // Act
            for ($i = 0; $i < $iterations; ++$i) {
                $id = IdGenerator::generate();

                // Assert
                expect($id)->toMatch('/^[0-9A-Z]+$/');
            }
        });

        test('rapid generation maintains uniqueness', function (): void {
            // Arrange
            $ids = [];
            $rapidIterations = 10_000;

            // Act
            for ($i = 0; $i < $rapidIterations; ++$i) {
                $ids[] = IdGenerator::generate();
            }

            // Assert
            $uniqueIds = array_unique($ids);
            expect($uniqueIds)->toHaveCount($rapidIterations);
        });

        test('isValid accepts ULID with lowercase characters', function (): void {
            // Arrange - Laravel's ULID validation is case-insensitive
            $uppercaseUlid = (string) Str::ulid();
            $lowercaseUlid = mb_strtolower($uppercaseUlid);

            // Act
            $result = IdGenerator::isValid($lowercaseUlid);

            // Assert - Laravel's isUlid() accepts both cases
            expect($result)->toBeTrue();
        });

        test('isValid returns false for whitespace string', function (): void {
            // Arrange
            $whitespace = '   ';

            // Act
            $result = IdGenerator::isValid($whitespace);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isValid returns false for ULID with spaces', function (): void {
            // Arrange
            $ulidWithSpaces = '01HQRZ9K3X ABCDEFGHIJKLM';

            // Act
            $result = IdGenerator::isValid($ulidWithSpaces);

            // Assert
            expect($result)->toBeFalse();
        });

        test('generated ULID timestamp component increases over time', function (): void {
            // Arrange
            $firstId = IdGenerator::generate();

            // Act
            usleep(2_000); // 2ms delay
            $secondId = IdGenerator::generate();

            // Assert - timestamp is encoded in first 10 characters
            $firstTimestamp = mb_substr($firstId, 0, 10);
            $secondTimestamp = mb_substr($secondId, 0, 10);
            expect($secondTimestamp)->toBeGreaterThanOrEqual($firstTimestamp);
        });

        test('isValid returns false for very long string', function (): void {
            // Arrange
            $longString = str_repeat('A', 100);

            // Act
            $result = IdGenerator::isValid($longString);

            // Assert
            expect($result)->toBeFalse();
        });

        test('generate returns string type not object', function (): void {
            // Arrange & Act
            $id = IdGenerator::generate();

            // Assert
            expect($id)->toBeString()
                ->and(is_object($id))->toBeFalse();
        });

        test('isValid returns false for special characters', function (): void {
            // Arrange
            $specialChars = '!@#$%^&*()_+-=[]{}|;:,.<>?';

            // Act
            $result = IdGenerator::isValid($specialChars);

            // Assert
            expect($result)->toBeFalse();
        });

        test('isValid returns false for null-like string', function (): void {
            // Arrange
            $nullString = 'null';

            // Act
            $result = IdGenerator::isValid($nullString);

            // Assert
            expect($result)->toBeFalse();
        });

        test('generated ULID contains valid Crockford Base32 characters only', function (): void {
            // Arrange
            $validCrockfordChars = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
            $iterations = 100;

            // Act
            for ($i = 0; $i < $iterations; ++$i) {
                $id = IdGenerator::generate();

                // Assert - ULID uses Crockford Base32 (excludes I, L, O, U)
                foreach (mb_str_split($id) as $char) {
                    expect(str_contains($validCrockfordChars, $char))->toBeTrue();
                }
            }
        });

        test('concurrent generation maintains uniqueness', function (): void {
            // Arrange
            $ids = [];
            $batchSize = 5_000;

            // Act - simulate concurrent generation
            for ($batch = 0; $batch < 3; ++$batch) {
                for ($i = 0; $i < $batchSize; ++$i) {
                    $ids[] = IdGenerator::generate();
                }
            }

            // Assert
            $uniqueIds = array_unique($ids);
            expect($uniqueIds)->toHaveCount($batchSize * 3);
        });
    });
});
