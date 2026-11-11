<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Identity;

use App\Tests\Unit\Shared\Domain\Identity\Fixture\OtherId;
use App\Tests\Unit\Shared\Domain\Identity\Fixture\SampleId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    private const string VALUE = '0193a5e0-7b1c-7a3e-9f00-0123456789ab';

    public function testKeepsTheValue(): void
    {
        $id = new SampleId(self::VALUE);

        self::assertSame(self::VALUE, $id->value);
        self::assertSame(self::VALUE, (string) $id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validValues(): iterable
    {
        yield 'version 7' => ['0193a5e0-7b1c-7a3e-9f00-0123456789ab'];
        yield 'version 4' => ['3f2b8c1e-9d4a-4e6f-8a1b-2c3d4e5f6a7b'];
        yield 'nil' => ['00000000-0000-0000-0000-000000000000'];
    }

    #[DataProvider('validValues')]
    public function testAcceptsCanonicalUuidOfAnyVersion(string $value): void
    {
        self::assertSame($value, (new SampleId($value))->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'not a uuid' => ['product-1'];
        yield 'uppercase' => ['0193A5E0-7B1C-7A3E-9F00-0123456789AB'];
        yield 'mixed case' => ['0193a5e0-7b1c-7a3e-9f00-0123456789aB'];
        yield 'no dashes' => ['0193a5e07b1c7a3e9f000123456789ab'];
        yield 'braces' => ['{0193a5e0-7b1c-7a3e-9f00-0123456789ab}'];
        yield 'too short' => ['0193a5e0-7b1c-7a3e-9f00-0123456789a'];
        yield 'too long' => ['0193a5e0-7b1c-7a3e-9f00-0123456789abc'];
        yield 'non-hex character' => ['0193a5e0-7b1c-7a3e-9f00-0123456789ag'];
        yield 'dash in the wrong place' => ['0193a5e07-b1c-7a3e-9f00-0123456789ab'];
        yield 'leading space' => [' 0193a5e0-7b1c-7a3e-9f00-0123456789ab'];
        yield 'trailing newline' => ["0193a5e0-7b1c-7a3e-9f00-0123456789ab\n"];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsAnythingButCanonicalLowercaseUuid(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SampleId($value);
    }

    public function testIdsOfTheSameTypeAndValueAreEqual(): void
    {
        self::assertTrue((new SampleId(self::VALUE))->equals(new SampleId(self::VALUE)));
    }

    public function testIdsWithDifferentValuesAreNotEqual(): void
    {
        $other = new SampleId('0193a5e0-7b1c-7a3e-9f00-0123456789ac');

        self::assertFalse((new SampleId(self::VALUE))->equals($other));
    }

    public function testIdsOfDifferentTypesAreNotEqualEvenWithTheSameValue(): void
    {
        $sample = new SampleId(self::VALUE);
        $other = new OtherId(self::VALUE);

        self::assertFalse($sample->equals($other));
        self::assertFalse($other->equals($sample));
    }
}
