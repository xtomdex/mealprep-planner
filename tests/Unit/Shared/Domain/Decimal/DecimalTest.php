<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Decimal;

use App\Shared\Domain\Decimal\Decimal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    public function testAddsWithoutFloatDrift(): void
    {
        // The classic float failure: 0.1 + 0.2 === 0.30000000000000004.
        self::assertSame('0.300000', Decimal::add('0.1', '0.2'));
    }

    public function testSubtractsBelowZero(): void
    {
        self::assertSame('-0.500000', Decimal::sub('1', '1.5'));
    }

    public function testMultiplies(): void
    {
        self::assertSame('3.750000', Decimal::mul('1.5', '2.5'));
    }

    public function testDivides(): void
    {
        self::assertSame('2.500000', Decimal::div('5', '2'));
    }

    public function testDivisionByZeroIsAnError(): void
    {
        $this->expectException(\DivisionByZeroError::class);

        Decimal::div('1', '0.000000');
    }

    public function testResultsAlwaysCarrySixFractionalDigits(): void
    {
        self::assertSame('3.000000', Decimal::add('1', '2'));
        self::assertSame(Decimal::SCALE, \strlen(explode('.', Decimal::mul('2', '2'))[1]));
    }

    public function testDigitsBeyondTheScaleAreCutOffNotRounded(): void
    {
        // 2 / 3 = 0.6666666…; rounding would end in 7.
        self::assertSame('0.666666', Decimal::div('2', '3'));
        self::assertSame('0.000000', Decimal::mul('0.001', '0.0001'));
    }

    public function testPrecisionSurvivesARoundTripWithinTheScale(): void
    {
        self::assertSame('0.999999', Decimal::mul(Decimal::div('1', '3'), '3'));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function comparisons(): iterable
    {
        yield 'less' => ['1.5', '2', -1];
        yield 'greater' => ['2', '1.5', 1];
        yield 'equal with different spelling' => ['2', '2.000000', 0];
        yield 'negative is less than zero' => ['-0.000001', '0', -1];
        yield 'difference beyond the scale is ignored' => ['1.0000001', '1', 0];
    }

    #[DataProvider('comparisons')]
    public function testCompares(string $a, string $b, int $expected): void
    {
        self::assertSame($expected, Decimal::cmp($a, $b));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function ceilings(): iterable
    {
        yield 'fraction goes up' => ['2.1', '3'];
        yield 'smallest fraction goes up' => ['2.000001', '3'];
        yield 'whole number stays' => ['2', '2'];
        yield 'whole number at working scale stays' => ['2.000000', '2'];
        yield 'zero stays' => ['0', '0'];
        yield 'below one goes to one' => ['0.25', '1'];
        yield 'negative goes towards zero' => ['-1.5', '-1'];
    }

    #[DataProvider('ceilings')]
    public function testCeilRoundsUpToAWholeNumber(string $value, string $expected): void
    {
        self::assertSame($expected, Decimal::ceil($value));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalizations(): iterable
    {
        yield 'trailing zeros' => ['1.500000', '1.5'];
        yield 'whole number at working scale' => ['2.000000', '2'];
        yield 'zero at working scale' => ['0.000000', '0'];
        yield 'already short' => ['1.5', '1.5'];
        yield 'integer zeros are kept' => ['100', '100'];
        yield 'integer zeros before the point are kept' => ['100.000000', '100'];
        yield 'inner zeros are kept' => ['1.050000', '1.05'];
        yield 'negative value' => ['-1.500000', '-1.5'];
        yield 'negative zero loses the sign' => ['-0.000000', '0'];
        yield 'plain zero' => ['0', '0'];
    }

    #[DataProvider('normalizations')]
    public function testNormalizeProducesTheShortestSpelling(string $value, string $expected): void
    {
        self::assertSame($expected, Decimal::normalize($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedValues(): iterable
    {
        yield 'empty' => [''];
        yield 'word' => ['abc'];
        yield 'decimal comma' => ['1,5'];
        yield 'exponent' => ['1e3'];
        yield 'leading plus' => ['+1'];
        yield 'bare fraction' => ['.5'];
        yield 'trailing point' => ['1.'];
        yield 'surrounding space' => [' 1'];
        yield 'two points' => ['1.2.3'];
        yield 'trailing newline' => ["1\n"];
    }

    #[DataProvider('malformedValues')]
    public function testRejectsMalformedInput(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Decimal::normalize($value);
    }

    public function testEveryOperationValidatesBothOperands(): void
    {
        $operations = [
            static fn (): string => Decimal::add('x', '1'),
            static fn (): string => Decimal::add('1', 'x'),
            static fn (): string => Decimal::sub('x', '1'),
            static fn (): string => Decimal::sub('1', 'x'),
            static fn (): string => Decimal::mul('x', '1'),
            static fn (): string => Decimal::mul('1', 'x'),
            static fn (): string => Decimal::div('x', '1'),
            static fn (): string => Decimal::div('1', 'x'),
            static fn (): int => Decimal::cmp('x', '1'),
            static fn (): int => Decimal::cmp('1', 'x'),
            static fn (): string => Decimal::ceil('x'),
        ];

        $rejected = 0;
        foreach ($operations as $operation) {
            try {
                $operation();
            } catch (\InvalidArgumentException) {
                ++$rejected;
            }
        }

        self::assertSame(\count($operations), $rejected);
    }
}
