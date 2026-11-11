<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Measurement;

use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MeasurementCategoryTest extends TestCase
{
    /**
     * @return iterable<string, array{MeasurementCategory, Unit}>
     */
    public static function canonicalUnits(): iterable
    {
        yield 'weight' => [MeasurementCategory::Weight, Unit::Gram];
        yield 'volume' => [MeasurementCategory::Volume, Unit::Millilitre];
        yield 'count' => [MeasurementCategory::Count, Unit::Piece];
        yield 'portion' => [MeasurementCategory::Portion, Unit::Portion];
    }

    #[DataProvider('canonicalUnits')]
    public function testEveryCategoryHasACanonicalUnit(MeasurementCategory $category, Unit $expected): void
    {
        self::assertSame($expected, $category->canonicalUnit());
    }

    /**
     * A new category added without a row above would otherwise go untested.
     */
    public function testTheTableAboveCoversEveryCategory(): void
    {
        self::assertCount(\count(MeasurementCategory::cases()), iterator_to_array(self::canonicalUnits()));
    }

    public function testCanonicalUnitBelongsToItsOwnCategory(): void
    {
        foreach (MeasurementCategory::cases() as $category) {
            self::assertSame($category, $category->canonicalUnit()->category());
        }
    }
}
