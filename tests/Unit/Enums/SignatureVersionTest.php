<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Webhook\Enums\SignatureVersion;

describe('SignatureVersion', function (): void {
    describe('enum cases', function (): void {
        test('has V1_HMAC case', function (): void {
            // Act
            $case = SignatureVersion::V1_HMAC;

            // Assert
            expect($case)->toBeInstanceOf(SignatureVersion::class)
                ->and($case->name)->toBe('V1_HMAC');
        });

        test('has V1A_ED25519 case', function (): void {
            // Act
            $case = SignatureVersion::V1A_ED25519;

            // Assert
            expect($case)->toBeInstanceOf(SignatureVersion::class)
                ->and($case->name)->toBe('V1A_ED25519');
        });

        test('has exactly two cases', function (): void {
            // Act
            $cases = SignatureVersion::cases();

            // Assert
            expect($cases)->toHaveCount(2)
                ->and($cases[0])->toBe(SignatureVersion::V1_HMAC)
                ->and($cases[1])->toBe(SignatureVersion::V1A_ED25519);
        });
    });

    describe('enum values', function (): void {
        test('V1_HMAC has correct value', function (): void {
            // Act
            $value = SignatureVersion::V1_HMAC->value;

            // Assert
            expect($value)->toBe('v1');
        });

        test('V1A_ED25519 has correct value', function (): void {
            // Act
            $value = SignatureVersion::V1A_ED25519->value;

            // Assert
            expect($value)->toBe('v1a');
        });
    });

    describe('from() method', function (): void {
        test('returns V1_HMAC for v1 value', function (): void {
            // Act
            $result = SignatureVersion::from('v1');

            // Assert
            expect($result)->toBe(SignatureVersion::V1_HMAC);
        });

        test('returns V1A_ED25519 for v1a value', function (): void {
            // Act
            $result = SignatureVersion::from('v1a');

            // Assert
            expect($result)->toBe(SignatureVersion::V1A_ED25519);
        });

        test('throws ValueError for invalid value', function (): void {
            // Act & Assert
            expect(fn () => SignatureVersion::from('invalid'))
                ->toThrow(ValueError::class);
        });

        test('throws ValueError for empty string', function (): void {
            // Act & Assert
            expect(fn () => SignatureVersion::from(''))
                ->toThrow(ValueError::class);
        });

        test('throws ValueError for v2', function (): void {
            // Act & Assert
            expect(fn () => SignatureVersion::from('v2'))
                ->toThrow(ValueError::class);
        });

        test('is case-sensitive - uppercase V1 fails', function (): void {
            // Act & Assert
            expect(fn () => SignatureVersion::from('V1'))
                ->toThrow(ValueError::class);
        });
    });

    describe('tryFrom() method', function (): void {
        test('returns V1_HMAC for v1 value', function (): void {
            // Act
            $result = SignatureVersion::tryFrom('v1');

            // Assert
            expect($result)->toBe(SignatureVersion::V1_HMAC);
        });

        test('returns V1A_ED25519 for v1a value', function (): void {
            // Act
            $result = SignatureVersion::tryFrom('v1a');

            // Assert
            expect($result)->toBe(SignatureVersion::V1A_ED25519);
        });

        test('returns null for invalid value', function (): void {
            // Act
            $result = SignatureVersion::tryFrom('invalid');

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null for empty string', function (): void {
            // Act
            $result = SignatureVersion::tryFrom('');

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null for v2', function (): void {
            // Act
            $result = SignatureVersion::tryFrom('v2');

            // Assert
            expect($result)->toBeNull();
        });

        test('is case-sensitive - uppercase V1 returns null', function (): void {
            // Act
            $result = SignatureVersion::tryFrom('V1');

            // Assert
            expect($result)->toBeNull();
        });

        test('is case-sensitive - uppercase V1A returns null', function (): void {
            // Act
            $result = SignatureVersion::tryFrom('V1A');

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('fromHeader() method - happy path', function (): void {
        test('returns V1A_ED25519 for v1a header', function (): void {
            // Arrange
            $header = 'v1a,signature123';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBe(SignatureVersion::V1A_ED25519);
        });

        test('returns V1_HMAC for v1 header', function (): void {
            // Arrange
            $header = 'v1,signature456';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBe(SignatureVersion::V1_HMAC);
        });

        test('prioritizes v1a over v1 when v1a comes first', function (): void {
            // Arrange
            $header = 'v1a,sig1 v1,sig2';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBe(SignatureVersion::V1A_ED25519);
        });

        test('handles v1a with empty signature', function (): void {
            // Arrange
            $header = 'v1a,';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBe(SignatureVersion::V1A_ED25519);
        });

        test('handles v1 with empty signature', function (): void {
            // Arrange
            $header = 'v1,';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBe(SignatureVersion::V1_HMAC);
        });
    });

    describe('fromHeader() method - invalid input', function (): void {
        test('returns null for empty string', function (): void {
            // Act
            $result = SignatureVersion::fromHeader('');

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null for v2 header', function (): void {
            // Arrange
            $header = 'v2,signature789';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null for malformed header without comma', function (): void {
            // Arrange
            $header = 'v1signature';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null for header with only version', function (): void {
            // Arrange
            $header = 'v1';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null for random string', function (): void {
            // Arrange
            $header = 'invalid-header-format';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('fromHeader() method - edge cases', function (): void {
        test('is case-sensitive - uppercase V1 returns null', function (): void {
            // Arrange
            $header = 'V1,signature';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBeNull();
        });

        test('is case-sensitive - uppercase V1A returns null', function (): void {
            // Arrange
            $header = 'V1A,signature';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBeNull();
        });

        test('handles header with multiple spaces', function (): void {
            // Arrange
            $header = 'v1,  signature  with  spaces  ';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBe(SignatureVersion::V1_HMAC);
        });

        test('handles header with special characters in signature', function (): void {
            // Arrange
            $header = 'v1a,sig!@#$%^&*()_+-={}[]|:";\'<>?,./';

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBe(SignatureVersion::V1A_ED25519);
        });

        test('handles very long signature', function (): void {
            // Arrange
            $signature = str_repeat('a', 10_000);
            $header = "v1,{$signature}";

            // Act
            $result = SignatureVersion::fromHeader($header);

            // Assert
            expect($result)->toBe(SignatureVersion::V1_HMAC);
        });
    });

    describe('prefix() method', function (): void {
        test('returns correct prefix for V1_HMAC', function (): void {
            // Act
            $prefix = SignatureVersion::V1_HMAC->prefix();

            // Assert
            expect($prefix)->toBe('v1,');
        });

        test('returns correct prefix for V1A_ED25519', function (): void {
            // Act
            $prefix = SignatureVersion::V1A_ED25519->prefix();

            // Assert
            expect($prefix)->toBe('v1a,');
        });

        test('prefix matches enum value plus comma', function (): void {
            // Arrange
            $version = SignatureVersion::V1_HMAC;

            // Act
            $prefix = $version->prefix();

            // Assert
            expect($prefix)->toBe($version->value.',');
        });
    });

    describe('isHmac() method', function (): void {
        test('returns true for V1_HMAC', function (): void {
            // Act
            $result = SignatureVersion::V1_HMAC->isHmac();

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false for V1A_ED25519', function (): void {
            // Act
            $result = SignatureVersion::V1A_ED25519->isHmac();

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('isEd25519() method', function (): void {
        test('returns true for V1A_ED25519', function (): void {
            // Act
            $result = SignatureVersion::V1A_ED25519->isEd25519();

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false for V1_HMAC', function (): void {
            // Act
            $result = SignatureVersion::V1_HMAC->isEd25519();

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('mutual exclusivity', function (): void {
        test('V1_HMAC is HMAC and not Ed25519', function (): void {
            // Act
            $version = SignatureVersion::V1_HMAC;

            // Assert
            expect($version->isHmac())->toBeTrue()
                ->and($version->isEd25519())->toBeFalse();
        });

        test('V1A_ED25519 is Ed25519 and not HMAC', function (): void {
            // Act
            $version = SignatureVersion::V1A_ED25519;

            // Assert
            expect($version->isEd25519())->toBeTrue()
                ->and($version->isHmac())->toBeFalse();
        });
    });

    describe('integration scenarios', function (): void {
        test('round-trip from header to prefix', function (): void {
            // Arrange
            $originalHeader = 'v1,original-signature';

            // Act
            $version = SignatureVersion::fromHeader($originalHeader);
            $newHeader = $version->prefix().'new-signature';

            // Assert
            expect($newHeader)->toStartWith('v1,')
                ->and(SignatureVersion::fromHeader($newHeader))->toBe($version);
        });

        test('identifies signature type from header', function (): void {
            // Arrange
            $hmacHeader = 'v1,hmac-sig';
            $ed25519Header = 'v1a,ed25519-sig';

            // Act
            $hmacVersion = SignatureVersion::fromHeader($hmacHeader);
            $ed25519Version = SignatureVersion::fromHeader($ed25519Header);

            // Assert
            expect($hmacVersion->isHmac())->toBeTrue()
                ->and($ed25519Version->isEd25519())->toBeTrue();
        });

        test('all cases can be retrieved and used', function (): void {
            // Act
            $cases = SignatureVersion::cases();

            // Assert
            foreach ($cases as $case) {
                expect($case)->toBeInstanceOf(SignatureVersion::class);
                expect($case->value)->toBeString();
                expect($case->prefix())->toContain(',');
                expect($case->isHmac() || $case->isEd25519())->toBeTrue();
            }
        });
    });
});
