<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Webhook\Enums\WebhookStatus;

describe('WebhookStatus', function (): void {
    describe('enum cases', function (): void {
        test('has PENDING case', function (): void {
            // Act
            $case = WebhookStatus::PENDING;

            // Assert
            expect($case)->toBeInstanceOf(WebhookStatus::class)
                ->and($case->name)->toBe('PENDING');
        });

        test('has PROCESSING case', function (): void {
            // Act
            $case = WebhookStatus::PROCESSING;

            // Assert
            expect($case)->toBeInstanceOf(WebhookStatus::class)
                ->and($case->name)->toBe('PROCESSING');
        });

        test('has PROCESSED case', function (): void {
            // Act
            $case = WebhookStatus::PROCESSED;

            // Assert
            expect($case)->toBeInstanceOf(WebhookStatus::class)
                ->and($case->name)->toBe('PROCESSED');
        });

        test('has FAILED case', function (): void {
            // Act
            $case = WebhookStatus::FAILED;

            // Assert
            expect($case)->toBeInstanceOf(WebhookStatus::class)
                ->and($case->name)->toBe('FAILED');
        });

        test('has exactly four cases', function (): void {
            // Act
            $cases = WebhookStatus::cases();

            // Assert
            expect($cases)->toHaveCount(4)
                ->and($cases[0])->toBe(WebhookStatus::PENDING)
                ->and($cases[1])->toBe(WebhookStatus::PROCESSING)
                ->and($cases[2])->toBe(WebhookStatus::PROCESSED)
                ->and($cases[3])->toBe(WebhookStatus::FAILED);
        });
    });

    describe('enum values', function (): void {
        test('PENDING has correct value', function (): void {
            // Act
            $value = WebhookStatus::PENDING->value;

            // Assert
            expect($value)->toBe('pending');
        });

        test('PROCESSING has correct value', function (): void {
            // Act
            $value = WebhookStatus::PROCESSING->value;

            // Assert
            expect($value)->toBe('processing');
        });

        test('PROCESSED has correct value', function (): void {
            // Act
            $value = WebhookStatus::PROCESSED->value;

            // Assert
            expect($value)->toBe('processed');
        });

        test('FAILED has correct value', function (): void {
            // Act
            $value = WebhookStatus::FAILED->value;

            // Assert
            expect($value)->toBe('failed');
        });
    });

    describe('from() method', function (): void {
        test('returns PENDING for pending value', function (): void {
            // Act
            $result = WebhookStatus::from('pending');

            // Assert
            expect($result)->toBe(WebhookStatus::PENDING);
        });

        test('returns PROCESSING for processing value', function (): void {
            // Act
            $result = WebhookStatus::from('processing');

            // Assert
            expect($result)->toBe(WebhookStatus::PROCESSING);
        });

        test('returns PROCESSED for processed value', function (): void {
            // Act
            $result = WebhookStatus::from('processed');

            // Assert
            expect($result)->toBe(WebhookStatus::PROCESSED);
        });

        test('returns FAILED for failed value', function (): void {
            // Act
            $result = WebhookStatus::from('failed');

            // Assert
            expect($result)->toBe(WebhookStatus::FAILED);
        });

        test('throws ValueError for invalid value', function (): void {
            // Act & Assert
            expect(fn () => WebhookStatus::from('invalid'))
                ->toThrow(ValueError::class);
        });

        test('throws ValueError for empty string', function (): void {
            // Act & Assert
            expect(fn () => WebhookStatus::from(''))
                ->toThrow(ValueError::class);
        });

        test('is case-sensitive - uppercase PENDING fails', function (): void {
            // Act & Assert
            expect(fn () => WebhookStatus::from('PENDING'))
                ->toThrow(ValueError::class);
        });
    });

    describe('tryFrom() method', function (): void {
        test('returns PENDING for pending value', function (): void {
            // Act
            $result = WebhookStatus::tryFrom('pending');

            // Assert
            expect($result)->toBe(WebhookStatus::PENDING);
        });

        test('returns PROCESSING for processing value', function (): void {
            // Act
            $result = WebhookStatus::tryFrom('processing');

            // Assert
            expect($result)->toBe(WebhookStatus::PROCESSING);
        });

        test('returns PROCESSED for processed value', function (): void {
            // Act
            $result = WebhookStatus::tryFrom('processed');

            // Assert
            expect($result)->toBe(WebhookStatus::PROCESSED);
        });

        test('returns FAILED for failed value', function (): void {
            // Act
            $result = WebhookStatus::tryFrom('failed');

            // Assert
            expect($result)->toBe(WebhookStatus::FAILED);
        });

        test('returns null for invalid value', function (): void {
            // Act
            $result = WebhookStatus::tryFrom('invalid');

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null for empty string', function (): void {
            // Act
            $result = WebhookStatus::tryFrom('');

            // Assert
            expect($result)->toBeNull();
        });

        test('is case-sensitive - uppercase PENDING returns null', function (): void {
            // Act
            $result = WebhookStatus::tryFrom('PENDING');

            // Assert
            expect($result)->toBeNull();
        });

        test('is case-sensitive - mixed case returns null', function (): void {
            // Act
            $result = WebhookStatus::tryFrom('Pending');

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('isTerminal() method - terminal states', function (): void {
        test('returns true for PROCESSED', function (): void {
            // Act
            $result = WebhookStatus::PROCESSED->isTerminal();

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns true for FAILED', function (): void {
            // Act
            $result = WebhookStatus::FAILED->isTerminal();

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('isTerminal() method - non-terminal states', function (): void {
        test('returns false for PENDING', function (): void {
            // Act
            $result = WebhookStatus::PENDING->isTerminal();

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false for PROCESSING', function (): void {
            // Act
            $result = WebhookStatus::PROCESSING->isTerminal();

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('canProcess() method - processable states', function (): void {
        test('returns true for PENDING', function (): void {
            // Act
            $result = WebhookStatus::PENDING->canProcess();

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns true for FAILED', function (): void {
            // Act
            $result = WebhookStatus::FAILED->canProcess();

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('canProcess() method - non-processable states', function (): void {
        test('returns false for PROCESSING', function (): void {
            // Act
            $result = WebhookStatus::PROCESSING->canProcess();

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false for PROCESSED', function (): void {
            // Act
            $result = WebhookStatus::PROCESSED->canProcess();

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('state transition logic', function (): void {
        test('PENDING can be processed and is not terminal', function (): void {
            // Act
            $status = WebhookStatus::PENDING;

            // Assert
            expect($status->canProcess())->toBeTrue()
                ->and($status->isTerminal())->toBeFalse();
        });

        test('PROCESSING cannot be processed and is not terminal', function (): void {
            // Act
            $status = WebhookStatus::PROCESSING;

            // Assert
            expect($status->canProcess())->toBeFalse()
                ->and($status->isTerminal())->toBeFalse();
        });

        test('PROCESSED cannot be processed and is terminal', function (): void {
            // Act
            $status = WebhookStatus::PROCESSED;

            // Assert
            expect($status->canProcess())->toBeFalse()
                ->and($status->isTerminal())->toBeTrue();
        });

        test('FAILED can be processed and is terminal', function (): void {
            // Act
            $status = WebhookStatus::FAILED;

            // Assert
            expect($status->canProcess())->toBeTrue()
                ->and($status->isTerminal())->toBeTrue();
        });
    });

    describe('retry logic scenarios', function (): void {
        test('identifies retryable states', function (): void {
            // Arrange
            $retryableStates = [];

            // Act
            foreach (WebhookStatus::cases() as $status) {
                if (!$status->canProcess()) {
                    continue;
                }

                $retryableStates[] = $status;
            }

            // Assert
            expect($retryableStates)->toHaveCount(2)
                ->and($retryableStates)->toContain(WebhookStatus::PENDING)
                ->and($retryableStates)->toContain(WebhookStatus::FAILED);
        });

        test('identifies non-retryable states', function (): void {
            // Arrange
            $nonRetryableStates = [];

            // Act
            foreach (WebhookStatus::cases() as $status) {
                if ($status->canProcess()) {
                    continue;
                }

                $nonRetryableStates[] = $status;
            }

            // Assert
            expect($nonRetryableStates)->toHaveCount(2)
                ->and($nonRetryableStates)->toContain(WebhookStatus::PROCESSING)
                ->and($nonRetryableStates)->toContain(WebhookStatus::PROCESSED);
        });
    });

    describe('terminal state scenarios', function (): void {
        test('identifies all terminal states', function (): void {
            // Arrange
            $terminalStates = [];

            // Act
            foreach (WebhookStatus::cases() as $status) {
                if (!$status->isTerminal()) {
                    continue;
                }

                $terminalStates[] = $status;
            }

            // Assert
            expect($terminalStates)->toHaveCount(2)
                ->and($terminalStates)->toContain(WebhookStatus::PROCESSED)
                ->and($terminalStates)->toContain(WebhookStatus::FAILED);
        });

        test('identifies all non-terminal states', function (): void {
            // Arrange
            $nonTerminalStates = [];

            // Act
            foreach (WebhookStatus::cases() as $status) {
                if ($status->isTerminal()) {
                    continue;
                }

                $nonTerminalStates[] = $status;
            }

            // Assert
            expect($nonTerminalStates)->toHaveCount(2)
                ->and($nonTerminalStates)->toContain(WebhookStatus::PENDING)
                ->and($nonTerminalStates)->toContain(WebhookStatus::PROCESSING);
        });
    });

    describe('edge cases', function (): void {
        test('all cases have lowercase values', function (): void {
            // Act
            $cases = WebhookStatus::cases();

            // Assert
            foreach ($cases as $case) {
                expect($case->value)->toBe(mb_strtolower($case->value));
            }
        });

        test('all case names are uppercase', function (): void {
            // Act
            $cases = WebhookStatus::cases();

            // Assert
            foreach ($cases as $case) {
                expect($case->name)->toBe(mb_strtoupper($case->name));
            }
        });

        test('case values match lowercase case names', function (): void {
            // Act
            $cases = WebhookStatus::cases();

            // Assert
            foreach ($cases as $case) {
                expect($case->value)->toBe(mb_strtolower($case->name));
            }
        });
    });

    describe('integration scenarios', function (): void {
        test('webhook lifecycle progression', function (): void {
            // Arrange - Start with PENDING
            $status = WebhookStatus::PENDING;

            // Assert - PENDING can be processed
            expect($status->canProcess())->toBeTrue();
            expect($status->isTerminal())->toBeFalse();

            // Act - Move to PROCESSING
            $status = WebhookStatus::PROCESSING;

            // Assert - PROCESSING cannot be reprocessed
            expect($status->canProcess())->toBeFalse();
            expect($status->isTerminal())->toBeFalse();

            // Act - Move to PROCESSED (success path)
            $status = WebhookStatus::PROCESSED;

            // Assert - PROCESSED is terminal and cannot be reprocessed
            expect($status->canProcess())->toBeFalse();
            expect($status->isTerminal())->toBeTrue();
        });

        test('webhook failure and retry progression', function (): void {
            // Arrange - Start with PENDING
            $status = WebhookStatus::PENDING;

            // Assert - PENDING can be processed
            expect($status->canProcess())->toBeTrue();

            // Act - Move to PROCESSING
            $status = WebhookStatus::PROCESSING;

            // Assert - PROCESSING cannot be reprocessed
            expect($status->canProcess())->toBeFalse();

            // Act - Move to FAILED (failure path)
            $status = WebhookStatus::FAILED;

            // Assert - FAILED can be retried even though it's terminal
            expect($status->canProcess())->toBeTrue();
            expect($status->isTerminal())->toBeTrue();
        });

        test('filtering webhooks by processable status', function (): void {
            // Arrange
            $statuses = [
                WebhookStatus::PENDING,
                WebhookStatus::PROCESSING,
                WebhookStatus::PROCESSED,
                WebhookStatus::FAILED,
            ];

            // Act
            $processable = array_filter($statuses, fn ($status) => $status->canProcess());

            // Assert
            expect($processable)->toHaveCount(2)
                ->and(array_values($processable))->toContain(WebhookStatus::PENDING)
                ->and(array_values($processable))->toContain(WebhookStatus::FAILED);
        });

        test('all cases can be serialized and deserialized', function (): void {
            // Act
            $cases = WebhookStatus::cases();

            // Assert
            foreach ($cases as $case) {
                $serialized = $case->value;
                $deserialized = WebhookStatus::from($serialized);
                expect($deserialized)->toBe($case);
            }
        });
    });
});
