<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Webhook\Server\Strategies\ExponentialBackoffStrategy;

describe('ExponentialBackoffStrategy', function (): void {
    describe('Happy Path - Exponential Backoff Calculation', function (): void {
        test('calculates exponential backoff correctly for attempt 1', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delay = $strategy->calculate(1);

            // Assert
            expect($delay)->toBe(1); // 1 * 2^(1-1) = 1 * 1 = 1
        });

        test('calculates exponential backoff correctly for attempt 2', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delay = $strategy->calculate(2);

            // Assert
            expect($delay)->toBe(2); // 1 * 2^(2-1) = 1 * 2 = 2
        });

        test('calculates exponential backoff correctly for attempt 3', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delay = $strategy->calculate(3);

            // Assert
            expect($delay)->toBe(4); // 1 * 2^(3-1) = 1 * 4 = 4
        });

        test('calculates exponential backoff correctly for attempt 4', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delay = $strategy->calculate(4);

            // Assert
            expect($delay)->toBe(8); // 1 * 2^(4-1) = 1 * 8 = 8
        });

        test('calculates exponential backoff correctly for attempt 5', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delay = $strategy->calculate(5);

            // Assert
            expect($delay)->toBe(16); // 1 * 2^(5-1) = 1 * 16 = 16
        });

        test('works with different base delay of 5 seconds', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 5,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delay1 = $strategy->calculate(1);
            $delay2 = $strategy->calculate(2);
            $delay3 = $strategy->calculate(3);

            // Assert
            expect($delay1)->toBe(5);  // 5 * 2^0 = 5
            expect($delay2)->toBe(10); // 5 * 2^1 = 10
            expect($delay3)->toBe(20); // 5 * 2^2 = 20
        });

        test('works with different base delay of 10 seconds', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 10,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delay1 = $strategy->calculate(1);
            $delay2 = $strategy->calculate(2);
            $delay3 = $strategy->calculate(3);

            // Assert
            expect($delay1)->toBe(10); // 10 * 2^0 = 10
            expect($delay2)->toBe(20); // 10 * 2^1 = 20
            expect($delay3)->toBe(40); // 10 * 2^2 = 40
        });

        test('produces exponential growth across multiple attempts', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delays = [];

            for ($attempt = 1; $attempt <= 10; ++$attempt) {
                $delays[$attempt] = $strategy->calculate($attempt);
            }

            // Assert
            expect($delays[1])->toBe(1);
            expect($delays[2])->toBe(2);
            expect($delays[3])->toBe(4);
            expect($delays[4])->toBe(8);
            expect($delays[5])->toBe(16);
            expect($delays[6])->toBe(32);
            expect($delays[7])->toBe(64);
            expect($delays[8])->toBe(128);
            expect($delays[9])->toBe(256);
            expect($delays[10])->toBe(512);
        });
    });

    describe('Happy Path - Max Delay Capping', function (): void {
        test('respects maximum delay cap when exponential delay exceeds it', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 100,
                useJitter: false,
            );

            // Act - attempt 10 would be 512 seconds, but max is 100
            $delay = $strategy->calculate(10);

            // Assert
            expect($delay)->toBe(100);
        });

        test('caps at max delay for very large attempts', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act - attempt 20 would be 524,288 seconds
            $delay = $strategy->calculate(20);

            // Assert
            expect($delay)->toBe(3_600);
        });

        test('does not cap when delay is below maximum', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 10_000,
                useJitter: false,
            );

            // Act
            $delay = $strategy->calculate(5);

            // Assert
            expect($delay)->toBe(16); // Not capped
        });

        test('caps correctly with custom max delay', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 10,
                maxDelaySeconds: 60,
                useJitter: false,
            );

            // Act
            $delay1 = $strategy->calculate(1); // 10
            $delay2 = $strategy->calculate(2); // 20
            $delay3 = $strategy->calculate(3); // 40
            $delay4 = $strategy->calculate(4); // 80 -> capped to 60
            $delay5 = $strategy->calculate(5); // 160 -> capped to 60

            // Assert
            expect($delay1)->toBe(10);
            expect($delay2)->toBe(20);
            expect($delay3)->toBe(40);
            expect($delay4)->toBe(60);
            expect($delay5)->toBe(60);
        });

        test('calculates delay exactly at max delay boundary', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 16,
                useJitter: false,
            );

            // Act
            $delay5 = $strategy->calculate(5); // Exactly 16
            $delay6 = $strategy->calculate(6); // Would be 32, capped to 16

            // Assert
            expect($delay5)->toBe(16);
            expect($delay6)->toBe(16);
        });
    });

    describe('Happy Path - Jitter Behavior', function (): void {
        test('adds jitter when enabled', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 100,
                maxDelaySeconds: 3_600,
                useJitter: true,
            );

            // Act - Run multiple times to detect jitter
            $delays = [];

            for ($i = 0; $i < 100; ++$i) {
                $delays[] = $strategy->calculate(3); // Base delay: 400
            }

            // Assert - Should have variation due to jitter
            $uniqueDelays = array_unique($delays);
            expect(count($uniqueDelays))->toBeGreaterThan(1);

            // All delays should be >= base delay (400)
            foreach ($delays as $delay) {
                expect($delay)->toBeGreaterThanOrEqual(400);
            }

            // All delays should be <= base delay + 25% jitter (400 + 100 = 500)
            foreach ($delays as $delay) {
                expect($delay)->toBeLessThanOrEqual(500);
            }
        });

        test('produces deterministic delays when jitter is disabled', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act - Run multiple times
            $delays = [];

            for ($i = 0; $i < 50; ++$i) {
                $delays[] = $strategy->calculate(5);
            }

            // Assert - All delays should be identical
            $uniqueDelays = array_unique($delays);
            expect(count($uniqueDelays))->toBe(1);
            expect($uniqueDelays[0])->toBe(16);
        });

        test('jitter stays within 0-25% range of calculated delay', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1_000,
                maxDelaySeconds: 10_000,
                useJitter: true,
            );

            // Act - Calculate for attempt 1 (base delay: 1000)
            $delays = [];

            for ($i = 0; $i < 200; ++$i) {
                $delays[] = $strategy->calculate(1);
            }

            // Assert
            $minDelay = min($delays);
            $maxDelay = max($delays);

            expect($minDelay)->toBeGreaterThanOrEqual(1_000);
            expect($maxDelay)->toBeLessThanOrEqual(1_250); // 1000 + 25%
        });

        test('jitter respects max delay cap', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 100,
                useJitter: true,
            );

            // Act - Large attempt that exceeds max delay
            $delays = [];

            for ($i = 0; $i < 100; ++$i) {
                $delays[] = $strategy->calculate(20);
            }

            // Assert - Even with jitter, should not exceed max delay + 25% of max delay
            foreach ($delays as $delay) {
                expect($delay)->toBeLessThanOrEqual(125); // 100 + 25
            }
        });

        test('default constructor uses jitter', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy();

            // Act
            $delays = [];

            for ($i = 0; $i < 50; ++$i) {
                $delays[] = $strategy->calculate(10);
            }

            // Assert - Should have variation (jitter enabled by default)
            $uniqueDelays = array_unique($delays);
            expect(count($uniqueDelays))->toBeGreaterThan(1);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles attempt 0 correctly', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delay = $strategy->calculate(0);

            // Assert
            expect($delay)->toBe(0); // 1 * 2^(0-1) = 1 * 0.5 = 0.5 -> int(0)
        });

        test('handles very large attempt numbers without overflow', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act - Attempt 50 would be astronomical, should cap at max
            $delay = $strategy->calculate(50);

            // Assert
            expect($delay)->toBe(3_600);
        });

        test('handles base delay of 0', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 0,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delay1 = $strategy->calculate(1);
            $delay5 = $strategy->calculate(5);
            $delay10 = $strategy->calculate(10);

            // Assert
            expect($delay1)->toBe(0);
            expect($delay5)->toBe(0);
            expect($delay10)->toBe(0);
        });

        test('handles max delay of 0', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 0,
                useJitter: false,
            );

            // Act
            $delay1 = $strategy->calculate(1);
            $delay5 = $strategy->calculate(5);

            // Assert
            expect($delay1)->toBe(0);
            expect($delay5)->toBe(0);
        });

        test('handles max delay smaller than base delay', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 100,
                maxDelaySeconds: 10,
                useJitter: false,
            );

            // Act
            $delay = $strategy->calculate(1);

            // Assert
            expect($delay)->toBe(10); // Capped to max even on first attempt
        });

        test('handles large base delay', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1_000,
                maxDelaySeconds: 10_000,
                useJitter: false,
            );

            // Act
            $delay1 = $strategy->calculate(1);
            $delay2 = $strategy->calculate(2);
            $delay3 = $strategy->calculate(3);
            $delay4 = $strategy->calculate(4);

            // Assert
            expect($delay1)->toBe(1_000);  // 1000 * 2^0
            expect($delay2)->toBe(2_000);  // 1000 * 2^1
            expect($delay3)->toBe(4_000);  // 1000 * 2^2
            expect($delay4)->toBe(8_000);  // 1000 * 2^3
        });

        test('handles negative attempt numbers', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delay = $strategy->calculate(-5);

            // Assert
            // 1 * 2^(-5-1) = 1 * 2^(-6) = 1 * 0.015625 = 0.015625 -> int(0)
            expect($delay)->toBe(0);
        });

        test('maintains immutability across multiple calculations', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: false,
            );

            // Act
            $delay1a = $strategy->calculate(5);
            $delay2 = $strategy->calculate(3);
            $delay1b = $strategy->calculate(5);

            // Assert - Same input should always produce same output
            expect($delay1a)->toBe($delay1b);
            expect($delay1a)->toBe(16);
            expect($delay2)->toBe(4);
        });

        test('uses default values when not specified', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy();

            // Act
            $delay = $strategy->calculate(1);

            // Assert - Default base is 1, should calculate correctly
            expect($delay)->toBeGreaterThanOrEqual(1);
            expect($delay)->toBeLessThanOrEqual(2); // 1 + 25% jitter = 1.25 -> int(1)
        });

        test('jitter with small delays produces valid results', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 3_600,
                useJitter: true,
            );

            // Act
            $delays = [];

            for ($i = 0; $i < 50; ++$i) {
                $delays[] = $strategy->calculate(1);
            }

            // Assert - All delays should be valid (>= 1, <= 1 + 25% = 1.25 -> 1)
            foreach ($delays as $delay) {
                expect($delay)->toBeGreaterThanOrEqual(1);
                expect($delay)->toBeLessThanOrEqual(2);
            }
        });

        test('boundary test at max delay with jitter', function (): void {
            // Arrange
            $strategy = new ExponentialBackoffStrategy(
                baseDelaySeconds: 1,
                maxDelaySeconds: 10,
                useJitter: true,
            );

            // Act
            $delays = [];

            for ($i = 0; $i < 100; ++$i) {
                $delays[] = $strategy->calculate(10); // Would be 512, capped to 10
            }

            // Assert - Should be capped at 10, with jitter up to 25% (10 + 2.5 = 12.5 -> 12)
            foreach ($delays as $delay) {
                expect($delay)->toBeGreaterThanOrEqual(10);
                expect($delay)->toBeLessThanOrEqual(13);
            }
        });
    });
});
