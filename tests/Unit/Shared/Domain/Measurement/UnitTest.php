<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Measurement;

use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnitTest extends TestCase
{
    /**
     * @return iterable<string, array{Unit, string, MeasurementCategory, Unit, string}>
     */
    public static function units(): iterable
    {
        yield 'gram' => [Unit::Gram, 'g', MeasurementCategory::Weight, Unit::Gram, '1'];
        yield 'kilogram' => [Unit::Kilogram, 'kg', MeasurementCategory::Weight, Unit::Gram, '1000'];
        yield 'millilitre' => [Unit::Millilitre, 'ml', MeasurementCategory::Volume, Unit::Millilitre, '1'];
        yield 'litre' => [Unit::Litre, 'l', MeasurementCategory::Volume, Unit::Millilitre, '1000'];
        yield 'piece' => [Unit::Piece, 'pcs', MeasurementCategory::Count, Unit::Piece, '1'];
        yield 'portion' => [Unit::Portion, 'portion', MeasurementCategory::Portion, Unit::Portion, '1'];
    }

    #[DataProvider('units')]
    public function testEveryUnitKnowsItsSymbolCategoryAndConversion(
        Unit $unit,
        string $symbol,
        MeasurementCategory $category,
        Unit $canonical,
        string $factor,
    ): void {
        self::assertSame($symbol, $unit->value);
        self::assertSame($category, $unit->category());
        self::assertSame($canonical, $unit->canonical());
        self::assertSame($factor, $unit->factorToCanonical());
    }

    /**
     * A new unit added without a row above would otherwise go untested.
     */
    public function testTheTableAboveCoversEveryUnit(): void
    {
        self::assertCount(\count(Unit::cases()), iterator_to_array(self::units()));
    }

    public function testCanonicalUnitIsItsOwnCanonicalWithFactorOne(): void
    {
        foreach (Unit::cases() as $unit) {
            $canonical = $unit->canonical();

            self::assertSame($canonical, $canonical->canonical());
            self::assertSame('1', $canonical->factorToCanonical());
        }
    }
}
