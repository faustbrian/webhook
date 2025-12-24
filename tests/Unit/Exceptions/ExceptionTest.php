<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Exceptions;

use Cline\Webhook\Client\Exceptions\InvalidSignatureException;
use Cline\Webhook\Client\Exceptions\WebhookNotFoundException;
use Cline\Webhook\Exceptions\Client\ExpiredTimestampException;
use Cline\Webhook\Exceptions\Client\FutureTimestampException;
use Cline\Webhook\Exceptions\Client\InvalidTimestampException;
use Cline\Webhook\Exceptions\Server\InvalidEd25519PrivateKeyException;
use Cline\Webhook\Exceptions\WebhookException;
use Cline\Webhook\Server\Exceptions\InvalidUrlException;
use Cline\Webhook\Server\Exceptions\MaxRetriesExceededException;
use Cline\Webhook\Server\Exceptions\WebhookCallException;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;
use Throwable;

use function describe;
use function expect;
use function str_repeat;
use function test;

/**
 * Comprehensive tests for all webhook exception classes.
 *
 * Tests cover:
 * - Exception hierarchy and interface implementation
 * - Static factory methods
 * - Exception messages
 * - Exception codes
 * - Additional properties (url, retries, previous exceptions)
 * - Edge cases: null messages, special characters, unicode
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
describe('WebhookException Interface', function (): void {
    test('is a Throwable interface', function (): void {
        // Arrange & Act
        $reflection = new ReflectionClass(WebhookException::class);

        // Assert
        expect($reflection->isInterface())->toBeTrue();
        expect($reflection->implementsInterface(Throwable::class))->toBeTrue();
    })->group('happy-path');

    test('can be caught as Throwable', function (): void {
        // Arrange
        $caught = false;

        // Act
        try {
            throw InvalidSignatureException::make('test-id');
        } catch (Throwable $e) {
            $caught = true;
        }

        // Assert
        expect($caught)->toBeTrue();
    })->group('happy-path');

    test('can be caught by WebhookException interface', function (): void {
        // Arrange
        $caught = false;

        // Act
        try {
            throw InvalidSignatureException::make('test-id');
        } catch (WebhookException $e) {
            $caught = true;
        }

        // Assert
        expect($caught)->toBeTrue();
    })->group('happy-path');
});

describe('InvalidTimestampException', function (): void {
    test('is abstract class', function (): void {
        // Arrange & Act
        $reflection = new ReflectionClass(InvalidTimestampException::class);

        // Assert
        expect($reflection->isAbstract())->toBeTrue();
    })->group('happy-path');

    test('extends InvalidArgumentException', function (): void {
        // Arrange & Act
        $exception = new ExpiredTimestampException('test');

        // Assert
        expect($exception)->toBeInstanceOf(InvalidArgumentException::class);
    })->group('happy-path');

    test('implements WebhookException interface', function (): void {
        // Arrange & Act
        $exception = new ExpiredTimestampException('test');

        // Assert
        expect($exception)->toBeInstanceOf(WebhookException::class);
    })->group('happy-path');
});

describe('ExpiredTimestampException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with fromTimestamps factory method', function (): void {
            // Arrange
            $timestamp = 1_000;
            $now = 1_500;
            $tolerance = 300;

            // Act
            $exception = ExpiredTimestampException::fromTimestamps($timestamp, $now, $tolerance);

            // Assert
            expect($exception)->toBeInstanceOf(ExpiredTimestampException::class);
            expect($exception->getMessage())->toBe('Webhook timestamp (1000) is too old. Age: 500s, Tolerance: 300s');
        })->group('happy-path');

        test('extends InvalidTimestampException', function (): void {
            // Arrange & Act
            $exception = ExpiredTimestampException::fromTimestamps(1_000, 1_500, 300);

            // Assert
            expect($exception)->toBeInstanceOf(InvalidTimestampException::class);
        })->group('happy-path');

        test('implements WebhookException interface', function (): void {
            // Arrange & Act
            $exception = ExpiredTimestampException::fromTimestamps(1_000, 1_500, 300);

            // Assert
            expect($exception)->toBeInstanceOf(WebhookException::class);
        })->group('happy-path');

        test('is final class', function (): void {
            // Arrange & Act
            $reflection = new ReflectionClass(ExpiredTimestampException::class);

            // Assert
            expect($reflection->isFinal())->toBeTrue();
        })->group('happy-path');
    });

    describe('Edge Cases', function (): void {
        test('handles zero timestamp', function (): void {
            // Arrange & Act
            $exception = ExpiredTimestampException::fromTimestamps(0, 1_000, 300);

            // Assert
            expect($exception->getMessage())->toBe('Webhook timestamp (0) is too old. Age: 1000s, Tolerance: 300s');
        })->group('edge-case');

        test('handles very large age difference', function (): void {
            // Arrange
            $timestamp = 100;
            $now = 999_999;
            $tolerance = 300;

            // Act
            $exception = ExpiredTimestampException::fromTimestamps($timestamp, $now, $tolerance);

            // Assert
            expect($exception->getMessage())->toContain('Age: 999899s');
        })->group('edge-case');

        test('handles zero tolerance', function (): void {
            // Arrange & Act
            $exception = ExpiredTimestampException::fromTimestamps(1_000, 1_001, 0);

            // Assert
            expect($exception->getMessage())->toContain('Tolerance: 0s');
        })->group('edge-case');

        test('calculates age correctly', function (): void {
            // Arrange
            $timestamp = 1_234_567_890;
            $now = 1_234_567_990;
            $tolerance = 60;

            // Act
            $exception = ExpiredTimestampException::fromTimestamps($timestamp, $now, $tolerance);

            // Assert
            expect($exception->getMessage())->toBe('Webhook timestamp (1234567890) is too old. Age: 100s, Tolerance: 60s');
        })->group('edge-case');
    });
});

describe('FutureTimestampException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with fromTimestamps factory method', function (): void {
            // Arrange
            $timestamp = 2_000;
            $now = 1_000;

            // Act
            $exception = FutureTimestampException::fromTimestamps($timestamp, $now);

            // Assert
            expect($exception)->toBeInstanceOf(FutureTimestampException::class);
            expect($exception->getMessage())->toBe('Webhook timestamp (2000) is in the future (current: 1000)');
        })->group('happy-path');

        test('extends InvalidTimestampException', function (): void {
            // Arrange & Act
            $exception = FutureTimestampException::fromTimestamps(2_000, 1_000);

            // Assert
            expect($exception)->toBeInstanceOf(InvalidTimestampException::class);
        })->group('happy-path');

        test('implements WebhookException interface', function (): void {
            // Arrange & Act
            $exception = FutureTimestampException::fromTimestamps(2_000, 1_000);

            // Assert
            expect($exception)->toBeInstanceOf(WebhookException::class);
        })->group('happy-path');

        test('is final class', function (): void {
            // Arrange & Act
            $reflection = new ReflectionClass(FutureTimestampException::class);

            // Assert
            expect($reflection->isFinal())->toBeTrue();
        })->group('happy-path');
    });

    describe('Edge Cases', function (): void {
        test('handles zero current time', function (): void {
            // Arrange & Act
            $exception = FutureTimestampException::fromTimestamps(1_000, 0);

            // Assert
            expect($exception->getMessage())->toBe('Webhook timestamp (1000) is in the future (current: 0)');
        })->group('edge-case');

        test('handles very large timestamp difference', function (): void {
            // Arrange
            $timestamp = 999_999_999;
            $now = 100;

            // Act
            $exception = FutureTimestampException::fromTimestamps($timestamp, $now);

            // Assert
            expect($exception->getMessage())->toContain('timestamp (999999999)');
            expect($exception->getMessage())->toContain('current: 100');
        })->group('edge-case');

        test('handles timestamp one second in future', function (): void {
            // Arrange & Act
            $exception = FutureTimestampException::fromTimestamps(1_001, 1_000);

            // Assert
            expect($exception->getMessage())->toBe('Webhook timestamp (1001) is in the future (current: 1000)');
        })->group('edge-case');
    });
});

describe('InvalidEd25519PrivateKeyException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with invalidFormat factory method', function (): void {
            // Arrange & Act
            $exception = InvalidEd25519PrivateKeyException::invalidFormat();

            // Assert
            expect($exception)->toBeInstanceOf(InvalidEd25519PrivateKeyException::class);
            expect($exception->getMessage())->toBe('Invalid Ed25519 private key format');
        })->group('happy-path');

        test('extends InvalidArgumentException', function (): void {
            // Arrange & Act
            $exception = InvalidEd25519PrivateKeyException::invalidFormat();

            // Assert
            expect($exception)->toBeInstanceOf(InvalidArgumentException::class);
        })->group('happy-path');

        test('implements WebhookException interface', function (): void {
            // Arrange & Act
            $exception = InvalidEd25519PrivateKeyException::invalidFormat();

            // Assert
            expect($exception)->toBeInstanceOf(WebhookException::class);
        })->group('happy-path');

        test('is final class', function (): void {
            // Arrange & Act
            $reflection = new ReflectionClass(InvalidEd25519PrivateKeyException::class);

            // Assert
            expect($reflection->isFinal())->toBeTrue();
        })->group('happy-path');
    });

    describe('Edge Cases', function (): void {
        test('can be caught and re-thrown', function (): void {
            // Arrange
            $caught = false;

            // Act
            try {
                throw InvalidEd25519PrivateKeyException::invalidFormat();
            } catch (InvalidEd25519PrivateKeyException $e) {
                $caught = true;
                expect($e->getMessage())->toBe('Invalid Ed25519 private key format');
            }

            // Assert
            expect($caught)->toBeTrue();
        })->group('edge-case');
    });
});

describe('InvalidSignatureException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with make factory method', function (): void {
            // Arrange
            $webhookId = 'msg_12345';

            // Act
            $exception = InvalidSignatureException::make($webhookId);

            // Assert
            expect($exception)->toBeInstanceOf(InvalidSignatureException::class);
            expect($exception->getMessage())->toBe('Invalid webhook signature for webhook ID: msg_12345');
        })->group('happy-path');

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = InvalidSignatureException::make('test-id');

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        })->group('happy-path');

        test('implements WebhookException interface', function (): void {
            // Arrange & Act
            $exception = InvalidSignatureException::make('test-id');

            // Assert
            expect($exception)->toBeInstanceOf(WebhookException::class);
        })->group('happy-path');

        test('is final class', function (): void {
            // Arrange & Act
            $reflection = new ReflectionClass(InvalidSignatureException::class);

            // Assert
            expect($reflection->isFinal())->toBeTrue();
        })->group('happy-path');
    });

    describe('Edge Cases', function (): void {
        test('handles empty webhook ID', function (): void {
            // Arrange & Act
            $exception = InvalidSignatureException::make('');

            // Assert
            expect($exception->getMessage())->toBe('Invalid webhook signature for webhook ID: ');
        })->group('edge-case');

        test('handles special characters in webhook ID', function (): void {
            // Arrange
            $webhookId = 'msg_!@#$%^&*()_+-={}[]|:";\'<>?,./';

            // Act
            $exception = InvalidSignatureException::make($webhookId);

            // Assert
            expect($exception->getMessage())->toContain($webhookId);
        })->group('edge-case');

        test('handles unicode characters in webhook ID', function (): void {
            // Arrange
            $webhookId = 'msg_世界_🌍_émojis';

            // Act
            $exception = InvalidSignatureException::make($webhookId);

            // Assert
            expect($exception->getMessage())->toContain($webhookId);
        })->group('edge-case');

        test('handles very long webhook ID', function (): void {
            // Arrange
            $webhookId = 'msg_'.str_repeat('x', 1_000);

            // Act
            $exception = InvalidSignatureException::make($webhookId);

            // Assert
            expect($exception->getMessage())->toContain($webhookId);
        })->group('edge-case');
    });
});

describe('WebhookNotFoundException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with make factory method', function (): void {
            // Arrange
            $webhookId = 'msg_67890';

            // Act
            $exception = WebhookNotFoundException::make($webhookId);

            // Assert
            expect($exception)->toBeInstanceOf(WebhookNotFoundException::class);
            expect($exception->getMessage())->toBe('Webhook not found: msg_67890');
        })->group('happy-path');

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = WebhookNotFoundException::make('test-id');

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        })->group('happy-path');

        test('implements WebhookException interface', function (): void {
            // Arrange & Act
            $exception = WebhookNotFoundException::make('test-id');

            // Assert
            expect($exception)->toBeInstanceOf(WebhookException::class);
        })->group('happy-path');

        test('is final class', function (): void {
            // Arrange & Act
            $reflection = new ReflectionClass(WebhookNotFoundException::class);

            // Assert
            expect($reflection->isFinal())->toBeTrue();
        })->group('happy-path');
    });

    describe('Edge Cases', function (): void {
        test('handles empty webhook ID', function (): void {
            // Arrange & Act
            $exception = WebhookNotFoundException::make('');

            // Assert
            expect($exception->getMessage())->toBe('Webhook not found: ');
        })->group('edge-case');

        test('handles special characters in webhook ID', function (): void {
            // Arrange
            $webhookId = 'id_<script>alert("xss")</script>';

            // Act
            $exception = WebhookNotFoundException::make($webhookId);

            // Assert
            expect($exception->getMessage())->toContain($webhookId);
        })->group('edge-case');

        test('handles unicode in webhook ID', function (): void {
            // Arrange
            $webhookId = '消息_🚀';

            // Act
            $exception = WebhookNotFoundException::make($webhookId);

            // Assert
            expect($exception->getMessage())->toContain($webhookId);
        })->group('edge-case');
    });
});

describe('InvalidUrlException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with make factory method', function (): void {
            // Arrange
            $url = 'invalid-url';

            // Act
            $exception = InvalidUrlException::make($url);

            // Assert
            expect($exception)->toBeInstanceOf(InvalidUrlException::class);
            expect($exception->getMessage())->toBe('Invalid webhook URL: invalid-url');
        })->group('happy-path');

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = InvalidUrlException::make('test-url');

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        })->group('happy-path');

        test('implements WebhookException interface', function (): void {
            // Arrange & Act
            $exception = InvalidUrlException::make('test-url');

            // Assert
            expect($exception)->toBeInstanceOf(WebhookException::class);
        })->group('happy-path');

        test('is final class', function (): void {
            // Arrange & Act
            $reflection = new ReflectionClass(InvalidUrlException::class);

            // Assert
            expect($reflection->isFinal())->toBeTrue();
        })->group('happy-path');
    });

    describe('Edge Cases', function (): void {
        test('handles empty URL', function (): void {
            // Arrange & Act
            $exception = InvalidUrlException::make('');

            // Assert
            expect($exception->getMessage())->toBe('Invalid webhook URL: ');
        })->group('edge-case');

        test('handles malformed URL', function (): void {
            // Arrange
            $url = 'ht!tp://not a valid url';

            // Act
            $exception = InvalidUrlException::make($url);

            // Assert
            expect($exception->getMessage())->toContain($url);
        })->group('edge-case');

        test('handles URL with special characters', function (): void {
            // Arrange
            $url = 'https://example.com/webhook?param=value&other=<script>alert(1)</script>';

            // Act
            $exception = InvalidUrlException::make($url);

            // Assert
            expect($exception->getMessage())->toContain($url);
        })->group('edge-case');

        test('handles URL with unicode', function (): void {
            // Arrange
            $url = 'https://例え.jp/webhook/路径';

            // Act
            $exception = InvalidUrlException::make($url);

            // Assert
            expect($exception->getMessage())->toContain($url);
        })->group('edge-case');

        test('handles very long URL', function (): void {
            // Arrange
            $url = 'https://example.com/'.str_repeat('a', 2_000);

            // Act
            $exception = InvalidUrlException::make($url);

            // Assert
            expect($exception->getMessage())->toContain('example.com');
        })->group('edge-case');
    });
});

describe('WebhookCallException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with dispatchFailed factory method', function (): void {
            // Arrange
            $url = 'https://example.com/webhook';

            // Act
            $exception = WebhookCallException::dispatchFailed($url);

            // Assert
            expect($exception)->toBeInstanceOf(WebhookCallException::class);
            expect($exception->getMessage())->toBe('Failed to dispatch webhook to: https://example.com/webhook');
        })->group('happy-path');

        test('creates exception with httpError factory method', function (): void {
            // Arrange
            $url = 'https://example.com/webhook';
            $statusCode = 500;
            $response = 'Internal Server Error';

            // Act
            $exception = WebhookCallException::httpError($url, $statusCode, $response);

            // Assert
            expect($exception)->toBeInstanceOf(WebhookCallException::class);
            expect($exception->getMessage())->toBe('Webhook call to https://example.com/webhook failed with HTTP 500: Internal Server Error');
        })->group('happy-path');

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = WebhookCallException::dispatchFailed('https://example.com');

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        })->group('happy-path');

        test('implements WebhookException interface', function (): void {
            // Arrange & Act
            $exception = WebhookCallException::dispatchFailed('https://example.com');

            // Assert
            expect($exception)->toBeInstanceOf(WebhookException::class);
        })->group('happy-path');

        test('is final class', function (): void {
            // Arrange & Act
            $reflection = new ReflectionClass(WebhookCallException::class);

            // Assert
            expect($reflection->isFinal())->toBeTrue();
        })->group('happy-path');

        test('dispatchFailed accepts previous exception', function (): void {
            // Arrange
            $url = 'https://example.com/webhook';
            $previous = new Exception('Connection timeout');

            // Act
            $exception = WebhookCallException::dispatchFailed($url, $previous);

            // Assert
            expect($exception->getPrevious())->toBe($previous);
            expect($exception->getMessage())->toBe('Failed to dispatch webhook to: https://example.com/webhook');
        })->group('happy-path');
    });

    describe('Sad Paths', function (): void {
        test('httpError with 4xx status code', function (): void {
            // Arrange
            $url = 'https://example.com/webhook';
            $statusCode = 404;
            $response = 'Not Found';

            // Act
            $exception = WebhookCallException::httpError($url, $statusCode, $response);

            // Assert
            expect($exception->getMessage())->toContain('404');
            expect($exception->getMessage())->toContain('Not Found');
        })->group('sad-path');

        test('httpError with 5xx status code', function (): void {
            // Arrange
            $url = 'https://example.com/webhook';
            $statusCode = 503;
            $response = 'Service Unavailable';

            // Act
            $exception = WebhookCallException::httpError($url, $statusCode, $response);

            // Assert
            expect($exception->getMessage())->toContain('503');
            expect($exception->getMessage())->toContain('Service Unavailable');
        })->group('sad-path');
    });

    describe('Edge Cases', function (): void {
        test('handles empty URL in dispatchFailed', function (): void {
            // Arrange & Act
            $exception = WebhookCallException::dispatchFailed('');

            // Assert
            expect($exception->getMessage())->toBe('Failed to dispatch webhook to: ');
        })->group('edge-case');

        test('handles null previous exception', function (): void {
            // Arrange & Act
            $exception = WebhookCallException::dispatchFailed('https://example.com', null);

            // Assert
            expect($exception->getPrevious())->toBeNull();
        })->group('edge-case');

        test('handles empty response in httpError', function (): void {
            // Arrange & Act
            $exception = WebhookCallException::httpError('https://example.com', 500, '');

            // Assert
            expect($exception->getMessage())->toContain('HTTP 500: ');
        })->group('edge-case');

        test('handles very long response in httpError', function (): void {
            // Arrange
            $longResponse = str_repeat('error', 500);

            // Act
            $exception = WebhookCallException::httpError('https://example.com', 500, $longResponse);

            // Assert
            expect($exception->getMessage())->toContain($longResponse);
        })->group('edge-case');

        test('handles special characters in response', function (): void {
            // Arrange
            $response = '{"error": "Invalid request", "details": "<script>alert(1)</script>"}';

            // Act
            $exception = WebhookCallException::httpError('https://example.com', 400, $response);

            // Assert
            expect($exception->getMessage())->toContain($response);
        })->group('edge-case');

        test('handles unicode in URL and response', function (): void {
            // Arrange
            $url = 'https://例え.jp/webhook';
            $response = 'エラーが発生しました';

            // Act
            $exception = WebhookCallException::httpError($url, 500, $response);

            // Assert
            expect($exception->getMessage())->toContain($url);
            expect($exception->getMessage())->toContain($response);
        })->group('edge-case');
    });
});

describe('MaxRetriesExceededException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception with make factory method', function (): void {
            // Arrange
            $maxRetries = 3;
            $url = 'https://example.com/webhook';

            // Act
            $exception = MaxRetriesExceededException::make($maxRetries, $url);

            // Assert
            expect($exception)->toBeInstanceOf(MaxRetriesExceededException::class);
            expect($exception->getMessage())->toBe('Maximum retry attempts (3) exceeded for webhook: https://example.com/webhook');
        })->group('happy-path');

        test('extends RuntimeException', function (): void {
            // Arrange & Act
            $exception = MaxRetriesExceededException::make(3, 'https://example.com');

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        })->group('happy-path');

        test('implements WebhookException interface', function (): void {
            // Arrange & Act
            $exception = MaxRetriesExceededException::make(3, 'https://example.com');

            // Assert
            expect($exception)->toBeInstanceOf(WebhookException::class);
        })->group('happy-path');

        test('is final class', function (): void {
            // Arrange & Act
            $reflection = new ReflectionClass(MaxRetriesExceededException::class);

            // Assert
            expect($reflection->isFinal())->toBeTrue();
        })->group('happy-path');
    });

    describe('Edge Cases', function (): void {
        test('handles zero retries', function (): void {
            // Arrange & Act
            $exception = MaxRetriesExceededException::make(0, 'https://example.com');

            // Assert
            expect($exception->getMessage())->toContain('(0)');
        })->group('edge-case');

        test('handles one retry', function (): void {
            // Arrange & Act
            $exception = MaxRetriesExceededException::make(1, 'https://example.com');

            // Assert
            expect($exception->getMessage())->toContain('(1)');
        })->group('edge-case');

        test('handles large number of retries', function (): void {
            // Arrange & Act
            $exception = MaxRetriesExceededException::make(999_999, 'https://example.com');

            // Assert
            expect($exception->getMessage())->toContain('(999999)');
        })->group('edge-case');

        test('handles empty URL', function (): void {
            // Arrange & Act
            $exception = MaxRetriesExceededException::make(3, '');

            // Assert
            expect($exception->getMessage())->toBe('Maximum retry attempts (3) exceeded for webhook: ');
        })->group('edge-case');

        test('handles special characters in URL', function (): void {
            // Arrange
            $url = 'https://example.com/webhook?param=value&other=<test>';

            // Act
            $exception = MaxRetriesExceededException::make(5, $url);

            // Assert
            expect($exception->getMessage())->toContain($url);
        })->group('edge-case');

        test('handles unicode in URL', function (): void {
            // Arrange
            $url = 'https://テスト.jp/webhook/パス';

            // Act
            $exception = MaxRetriesExceededException::make(3, $url);

            // Assert
            expect($exception->getMessage())->toContain($url);
        })->group('edge-case');

        test('message format is correct', function (): void {
            // Arrange & Act
            $exception = MaxRetriesExceededException::make(10, 'https://api.example.com/webhooks/endpoint');

            // Assert
            expect($exception->getMessage())
                ->toBe('Maximum retry attempts (10) exceeded for webhook: https://api.example.com/webhooks/endpoint');
        })->group('edge-case');
    });
});
