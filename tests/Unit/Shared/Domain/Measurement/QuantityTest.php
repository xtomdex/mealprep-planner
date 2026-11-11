<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Measurement;

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function testKeepsAmountAndUnit(): void
    {
        $quantity = Quantity::of('1.5', Unit::Kilogram);

        self::assertSame('1.5', $quantity->amount);
        self::assertSame(Unit::Kilogram, $quantity->unit);
    }

    public function testAmountIsStoredInItsShortestSpelling(): void
    {
        self::assertSame('2', Quantity::of('2.000000', Unit::Gram)->amount);
        self::assertSame('0.5', Quantity::of('0.50', Unit::Litre)->amount);
    }

    public function testAmountIsCutAtTheWorkingScale(): void
    {
        self::assertSame('1.123456', Quantity::of('1.1234567', Unit::Gram)->amount);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonPositiveAmounts(): iterable
    {
        yield 'zero' => ['0'];
        yield 'zero at working scale' => ['0.000000'];
        yield 'negative' => ['-1'];
        yield 'negative fraction' => ['-0.000001'];
        yield 'positive only beyond the working scale' => ['0.0000001'];
    }

    #[DataProvider('nonPositiveAmounts')]
    public function testQty01RejectsAmountThatIsNotGreaterThanZero(string $amount): void
    {
        try {
            Quantity::of($amount, Unit::Gram);
            self::fail('QTY-01 was not enforced.');
        } catch (ValidationException $exception) {
            self::assertSame('QTY-01', $exception->errorCode());
            self::assertSame('amount', $exception->field());
        }
    }

    public function testMalformedAmountIsAProgrammingErrorNotAValidationError(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Quantity::of('1,5', Unit::Kilogram);
    }

    /**
     * @return iterable<string, array{string, Unit, string, Unit}>
     */
    public static function conversions(): iterable
    {
        yield 'kilograms to grams' => ['1.5', Unit::Kilogram, '1500', Unit::Gram];
        yield 'small weight keeps precision' => ['0.0005', Unit::Kilogram, '0.5', Unit::Gram];
        yield 'litres to millilitres' => ['0.25', Unit::Litre, '250', Unit::Millilitre];
        yield 'grams stay grams' => ['250', Unit::Gram, '250', Unit::Gram];
        yield 'millilitres stay millilitres' => ['40', Unit::Millilitre, '40', Unit::Millilitre];
        yield 'pieces stay pieces' => ['3', Unit::Piece, '3', Unit::Piece];
        yield 'portions stay portions' => ['2', Unit::Portion, '2', Unit::Portion];
    }

    #[DataProvider('conversions')]
    public function testConvertsToTheCanonicalUnit(string $amount, Unit $unit, string $expectedAmount, Unit $expectedUnit): void
    {
        $canonical = Quantity::of($amount, $unit)->toCanonical();

        self::assertSame($expectedAmount, $canonical->amount);
        self::assertSame($expectedUnit, $canonical->unit);
    }

    public function testAddsKilogramsAndGramsIntoGrams(): void
    {
        $sum = Quantity::of('1', Unit::Kilogram)->add(Quantity::of('250', Unit::Gram));

        self::assertSame('1250 g', (string) $sum);
    }

    public function testAddsLitresAndMillilitresIntoMillilitres(): void
    {
        $sum = Quantity::of('200', Unit::Millilitre)->add(Quantity::of('0.5', Unit::Litre));

        self::assertSame('700 ml', (string) $sum);
    }

    public function testSumIsCanonicalEvenWhenBothOperandsUseTheLargerUnit(): void
    {
        $sum = Quantity::of('1', Unit::Kilogram)->add(Quantity::of('0.5', Unit::Kilogram));

        self::assertSame('1500 g', (string) $sum);
    }

    public function testAddsWithoutFloatDrift(): void
    {
        $sum = Quantity::of('0.1', Unit::Gram)->add(Quantity::of('0.2', Unit::Gram));

        self::assertSame('0.3', $sum->amount);
    }

    public function testAddDoesNotChangeTheOperands(): void
    {
        $a = Quantity::of('1', Unit::Kilogram);
        $b = Quantity::of('250', Unit::Gram);
        $a->add($b);

        self::assertSame('1 kg', (string) $a);
        self::assertSame('250 g', (string) $b);
    }

    /**
     * @return iterable<string, array{Quantity, Quantity}>
     */
    public static function incompatiblePairs(): iterable
    {
        yield 'weight and volume' => [Quantity::of('100', Unit::Gram), Quantity::of('100', Unit::Millilitre)];
        yield 'larger units of weight and volume' => [Quantity::of('1', Unit::Kilogram), Quantity::of('1', Unit::Litre)];
        yield 'count and portion' => [Quantity::of('2', Unit::Piece), Quantity::of('2', Unit::Portion)];
        yield 'count and weight' => [Quantity::of('2', Unit::Piece), Quantity::of('100', Unit::Gram)];
    }

    #[DataProvider('incompatiblePairs')]
    public function testQty02RejectsAddingAcrossCategoriesInEitherOrder(Quantity $a, Quantity $b): void
    {
        foreach ([[$a, $b], [$b, $a]] as [$left, $right]) {
            try {
                $left->add($right);
                self::fail('QTY-02 was not enforced.');
            } catch (ValidationException $exception) {
                self::assertSame('QTY-02', $exception->errorCode());
                self::assertNull($exception->field());
            }
        }
    }

    public function testQty02MessageNamesBothQuantities(): void
    {
        $this->expectExceptionMessage('Cannot add 100 g and 50 ml: incompatible units');

        Quantity::of('100', Unit::Gram)->add(Quantity::of('50', Unit::Millilitre));
    }

    /**
     * @return iterable<string, array{string, Unit, string, string}>
     */
    public static function multiplications(): iterable
    {
        yield 'whole factor' => ['150', Unit::Gram, '4', '600 g'];
        yield 'fractional factor' => ['200', Unit::Gram, '1.5', '300 g'];
        yield 'factor below one' => ['1', Unit::Kilogram, '0.25', '0.25 kg'];
        yield 'fractional amount and factor' => ['0.5', Unit::Litre, '0.5', '0.25 l'];
        yield 'pieces may become fractional' => ['3', Unit::Piece, '0.5', '1.5 pcs'];
    }

    #[DataProvider('multiplications')]
    public function testMultipliesTheAmountAndKeepsTheUnit(string $amount, Unit $unit, string $factor, string $expected): void
    {
        self::assertSame($expected, (string) Quantity::of($amount, $unit)->multiply($factor));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonPositiveFactors(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-2'];
        yield 'so small that the product vanishes' => ['0.0000001'];
    }

    #[DataProvider('nonPositiveFactors')]
    public function testQty01RejectsMultiplyingIntoANonPositiveAmount(string $factor): void
    {
        try {
            Quantity::of('1', Unit::Gram)->multiply($factor);
            self::fail('QTY-01 was not enforced.');
        } catch (ValidationException $exception) {
            self::assertSame('QTY-01', $exception->errorCode());
        }
    }

    /**
     * @return iterable<string, array{string, Unit, string}>
     */
    public static function roundings(): iterable
    {
        yield 'fraction goes up' => ['333.33', Unit::Gram, '334 g'];
        yield 'smallest fraction goes up' => ['2.000001', Unit::Piece, '3 pcs'];
        yield 'whole amount stays' => ['250', Unit::Gram, '250 g'];
        yield 'below one becomes one' => ['0.2', Unit::Piece, '1 pcs'];
        yield 'rounds in the current unit, not the canonical one' => ['1.2', Unit::Kilogram, '2 kg'];
    }

    #[DataProvider('roundings')]
    public function testRoundsUpToAWholeNumberOfTheCurrentUnit(string $amount, Unit $unit, string $expected): void
    {
        self::assertSame($expected, (string) Quantity::of($amount, $unit)->roundUpToWhole());
    }

    public function testEqualAcrossUnitsOfTheSameCategory(): void
    {
        self::assertTrue(Quantity::of('1', Unit::Kilogram)->equals(Quantity::of('1000', Unit::Gram)));
        self::assertTrue(Quantity::of('500', Unit::Millilitre)->equals(Quantity::of('0.5', Unit::Litre)));
    }

    public function testEqualRegardlessOfSpelling(): void
    {
        self::assertTrue(Quantity::of('2', Unit::Gram)->equals(Quantity::of('2.000000', Unit::Gram)));
    }

    public function testNotEqualWhenAmountsDiffer(): void
    {
        self::assertFalse(Quantity::of('1', Unit::Kilogram)->equals(Quantity::of('999', Unit::Gram)));
    }

    public function testNotEqualAcrossCategoriesEvenWithTheSameNumber(): void
    {
        self::assertFalse(Quantity::of('100', Unit::Gram)->equals(Quantity::of('100', Unit::Millilitre)));
        self::assertFalse(Quantity::of('1', Unit::Piece)->equals(Quantity::of('1', Unit::Portion)));
    }

    /**
     * @return iterable<string, array{string, Unit, string}>
     */
    public static function stringForms(): iterable
    {
        yield 'fraction' => ['1.5', Unit::Kilogram, '1.5 kg'];
        yield 'whole' => ['250', Unit::Gram, '250 g'];
        yield 'trailing zeros are dropped' => ['1.500000', Unit::Litre, '1.5 l'];
        yield 'pieces' => ['3', Unit::Piece, '3 pcs'];
        yield 'portions' => ['2', Unit::Portion, '2 portion'];
    }

    #[DataProvider('stringForms')]
    public function testStringFormIsTheNormalizedAmountAndTheUnit(string $amount, Unit $unit, string $expected): void
    {
        self::assertSame($expected, (string) Quantity::of($amount, $unit));
    }
}
