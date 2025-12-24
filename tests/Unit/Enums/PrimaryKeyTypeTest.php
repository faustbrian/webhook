<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Webhook\Enums\PrimaryKeyType;

describe('PrimaryKeyType', function (): void {
    describe('enum cases', function (): void {
        test('has ID case', function (): void {
            // Act
            $case = PrimaryKeyType::ID;

            // Assert
            expect($case)->toBeInstanceOf(PrimaryKeyType::class)
                ->and($case->name)->toBe('ID');
        });

        test('has ULID case', function (): void {
            // Act
            $case = PrimaryKeyType::ULID;

            // Assert
            expect($case)->toBeInstanceOf(PrimaryKeyType::class)
                ->and($case->name)->toBe('ULID');
        });

        test('has UUID case', function (): void {
            // Act
            $case = PrimaryKeyType::UUID;

            // Assert
            expect($case)->toBeInstanceOf(PrimaryKeyType::class)
                ->and($case->name)->toBe('UUID');
        });

        test('has exactly three cases', function (): void {
            // Act
            $cases = PrimaryKeyType::cases();

            // Assert
            expect($cases)->toHaveCount(3)
                ->and($cases[0])->toBe(PrimaryKeyType::ID)
                ->and($cases[1])->toBe(PrimaryKeyType::ULID)
                ->and($cases[2])->toBe(PrimaryKeyType::UUID);
        });
    });

    describe('enum values', function (): void {
        test('ID has correct value', function (): void {
            // Act
            $value = PrimaryKeyType::ID->value;

            // Assert
            expect($value)->toBe('id');
        });

        test('ULID has correct value', function (): void {
            // Act
            $value = PrimaryKeyType::ULID->value;

            // Assert
            expect($value)->toBe('ulid');
        });

        test('UUID has correct value', function (): void {
            // Act
            $value = PrimaryKeyType::UUID->value;

            // Assert
            expect($value)->toBe('uuid');
        });
    });

    describe('from() method', function (): void {
        test('returns ID for id value', function (): void {
            // Act
            $result = PrimaryKeyType::from('id');

            // Assert
            expect($result)->toBe(PrimaryKeyType::ID);
        });

        test('returns ULID for ulid value', function (): void {
            // Act
            $result = PrimaryKeyType::from('ulid');

            // Assert
            expect($result)->toBe(PrimaryKeyType::ULID);
        });

        test('returns UUID for uuid value', function (): void {
            // Act
            $result = PrimaryKeyType::from('uuid');

            // Assert
            expect($result)->toBe(PrimaryKeyType::UUID);
        });

        test('throws ValueError for invalid value', function (): void {
            // Act & Assert
            expect(fn () => PrimaryKeyType::from('invalid'))
                ->toThrow(ValueError::class);
        });

        test('throws ValueError for empty string', function (): void {
            // Act & Assert
            expect(fn () => PrimaryKeyType::from(''))
                ->toThrow(ValueError::class);
        });

        test('is case-sensitive - uppercase ID fails', function (): void {
            // Act & Assert
            expect(fn () => PrimaryKeyType::from('ID'))
                ->toThrow(ValueError::class);
        });

        test('is case-sensitive - uppercase ULID fails', function (): void {
            // Act & Assert
            expect(fn () => PrimaryKeyType::from('ULID'))
                ->toThrow(ValueError::class);
        });

        test('is case-sensitive - uppercase UUID fails', function (): void {
            // Act & Assert
            expect(fn () => PrimaryKeyType::from('UUID'))
                ->toThrow(ValueError::class);
        });
    });

    describe('tryFrom() method', function (): void {
        test('returns ID for id value', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('id');

            // Assert
            expect($result)->toBe(PrimaryKeyType::ID);
        });

        test('returns ULID for ulid value', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('ulid');

            // Assert
            expect($result)->toBe(PrimaryKeyType::ULID);
        });

        test('returns UUID for uuid value', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('uuid');

            // Assert
            expect($result)->toBe(PrimaryKeyType::UUID);
        });

        test('returns null for invalid value', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('invalid');

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null for empty string', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('');

            // Assert
            expect($result)->toBeNull();
        });

        test('is case-sensitive - uppercase ID returns null', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('ID');

            // Assert
            expect($result)->toBeNull();
        });

        test('is case-sensitive - uppercase ULID returns null', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('ULID');

            // Assert
            expect($result)->toBeNull();
        });

        test('is case-sensitive - uppercase UUID returns null', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('UUID');

            // Assert
            expect($result)->toBeNull();
        });

        test('is case-sensitive - mixed case returns null', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('Ulid');

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('edge cases', function (): void {
        test('all cases have lowercase values', function (): void {
            // Act
            $cases = PrimaryKeyType::cases();

            // Assert
            foreach ($cases as $case) {
                expect($case->value)->toBe(mb_strtolower($case->value));
            }
        });

        test('all case names are uppercase', function (): void {
            // Act
            $cases = PrimaryKeyType::cases();

            // Assert
            foreach ($cases as $case) {
                expect($case->name)->toBe(mb_strtoupper($case->name));
            }
        });

        test('case values match lowercase case names', function (): void {
            // Act
            $cases = PrimaryKeyType::cases();

            // Assert
            foreach ($cases as $case) {
                expect($case->value)->toBe(mb_strtolower($case->name));
            }
        });

        test('rejects guid variant', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('guid');

            // Assert
            expect($result)->toBeNull();
        });

        test('rejects integer variant', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('integer');

            // Assert
            expect($result)->toBeNull();
        });

        test('rejects bigint variant', function (): void {
            // Act
            $result = PrimaryKeyType::tryFrom('bigint');

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('identifier type characteristics', function (): void {
        test('ID represents auto-incrementing integer', function (): void {
            // Arrange
            $type = PrimaryKeyType::ID;

            // Assert
            expect($type->value)->toBe('id')
                ->and($type->name)->toBe('ID');
        });

        test('ULID represents lexicographically sortable identifier', function (): void {
            // Arrange
            $type = PrimaryKeyType::ULID;

            // Assert
            expect($type->value)->toBe('ulid')
                ->and($type->name)->toBe('ULID');
        });

        test('UUID represents universally unique identifier', function (): void {
            // Arrange
            $type = PrimaryKeyType::UUID;

            // Assert
            expect($type->value)->toBe('uuid')
                ->and($type->name)->toBe('UUID');
        });
    });

    describe('configuration scenarios', function (): void {
        test('can be used in configuration arrays', function (): void {
            // Arrange
            $config = [
                'default' => PrimaryKeyType::ULID->value,
                'fallback' => PrimaryKeyType::ID->value,
            ];

            // Act
            $defaultType = PrimaryKeyType::from($config['default']);
            $fallbackType = PrimaryKeyType::from($config['fallback']);

            // Assert
            expect($defaultType)->toBe(PrimaryKeyType::ULID)
                ->and($fallbackType)->toBe(PrimaryKeyType::ID);
        });

        test('all types can be serialized to database', function (): void {
            // Act
            $types = [
                PrimaryKeyType::ID->value,
                PrimaryKeyType::ULID->value,
                PrimaryKeyType::UUID->value,
            ];

            // Assert
            expect($types)->toHaveCount(3)
                ->and($types)->toContain('id')
                ->and($types)->toContain('ulid')
                ->and($types)->toContain('uuid');
        });

        test('can validate user input', function (): void {
            // Arrange
            $validInputs = ['id', 'ulid', 'uuid'];
            $invalidInputs = ['ID', 'guid', 'integer', '', 'unknown'];

            // Act & Assert
            foreach ($validInputs as $input) {
                expect(PrimaryKeyType::tryFrom($input))->not->toBeNull();
            }

            foreach ($invalidInputs as $input) {
                expect(PrimaryKeyType::tryFrom($input))->toBeNull();
            }
        });
    });

    describe('integration scenarios', function (): void {
        test('round-trip serialization', function (): void {
            // Arrange
            $cases = PrimaryKeyType::cases();

            // Act & Assert
            foreach ($cases as $case) {
                $serialized = $case->value;
                $deserialized = PrimaryKeyType::from($serialized);
                expect($deserialized)->toBe($case);
            }
        });

        test('can be used to determine model key type', function (): void {
            // Arrange
            $modelConfigs = [
                'webhook_calls' => PrimaryKeyType::ULID,
                'webhook_logs' => PrimaryKeyType::UUID,
                'webhook_stats' => PrimaryKeyType::ID,
            ];

            // Act & Assert
            foreach ($modelConfigs as $table => $keyType) {
                expect($keyType)->toBeInstanceOf(PrimaryKeyType::class);
                expect($keyType->value)->toBeIn(['id', 'ulid', 'uuid']);
            }
        });

        test('filtering types by value pattern', function (): void {
            // Arrange
            $cases = PrimaryKeyType::cases();

            // Act - Find types with 'u' prefix
            $uTypes = array_filter($cases, fn ($case) => str_starts_with($case->value, 'u'));

            // Assert
            expect($uTypes)->toHaveCount(2)
                ->and(array_values($uTypes))->toContain(PrimaryKeyType::ULID)
                ->and(array_values($uTypes))->toContain(PrimaryKeyType::UUID);
        });

        test('mapping enum to migration column types', function (): void {
            // Arrange
            $migrationTypes = [
                PrimaryKeyType::ID->value => 'bigIncrements',
                PrimaryKeyType::ULID->value => 'ulid',
                PrimaryKeyType::UUID->value => 'uuid',
            ];

            // Act & Assert
            expect($migrationTypes)->toHaveCount(3)
                ->and($migrationTypes['id'])->toBe('bigIncrements')
                ->and($migrationTypes['ulid'])->toBe('ulid')
                ->and($migrationTypes['uuid'])->toBe('uuid');
        });
    });

    describe('comparison scenarios', function (): void {
        test('enum instances are strictly equal to themselves', function (): void {
            // Act
            $id1 = PrimaryKeyType::ID;
            $id2 = PrimaryKeyType::ID;

            // Assert
            expect($id1)->toBe($id2)
                ->and($id1 === $id2)->toBeTrue();
        });

        test('different enum instances are not equal', function (): void {
            // Act
            $id = PrimaryKeyType::ID;
            $ulid = PrimaryKeyType::ULID;

            // Assert
            expect($id)->not->toBe($ulid)
                ->and($id === $ulid)->toBeFalse();
        });

        test('can distinguish between all types', function (): void {
            // Arrange
            $types = PrimaryKeyType::cases();

            // Act & Assert
            for ($i = 0; $i < count($types); ++$i) {
                for ($j = $i + 1; $j < count($types); ++$j) {
                    expect($types[$i])->not->toBe($types[$j]);
                }
            }
        });
    });

    describe('value validation', function (): void {
        test('rejects numeric strings', function (): void {
            // Act & Assert
            expect(PrimaryKeyType::tryFrom('1'))->toBeNull();
            expect(PrimaryKeyType::tryFrom('123'))->toBeNull();
        });

        test('rejects whitespace variations', function (): void {
            // Act & Assert
            expect(PrimaryKeyType::tryFrom(' id'))->toBeNull();
            expect(PrimaryKeyType::tryFrom('id '))->toBeNull();
            expect(PrimaryKeyType::tryFrom(' ulid '))->toBeNull();
        });

        test('rejects partial matches', function (): void {
            // Act & Assert
            expect(PrimaryKeyType::tryFrom('ul'))->toBeNull();
            expect(PrimaryKeyType::tryFrom('uu'))->toBeNull();
            expect(PrimaryKeyType::tryFrom('i'))->toBeNull();
        });

        test('rejects hyphenated variants', function (): void {
            // Act & Assert
            expect(PrimaryKeyType::tryFrom('u-lid'))->toBeNull();
            expect(PrimaryKeyType::tryFrom('u-uid'))->toBeNull();
        });
    });
});
