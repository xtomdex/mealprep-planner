<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\Planning;

use App\Planning\Domain\Catalog\ComponentBom;
use App\Planning\Domain\Catalog\DishComposition;
use App\Planning\Domain\Catalog\ProductInfo;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Planning\InMemoryCatalogReader;
use PHPUnit\Framework\TestCase;

/**
 * Pins the CatalogReader contract on the fake: only known ids come back, an
 * unknown id is silently left out, and the order of the answer carries no
 * meaning.
 */
final class InMemoryCatalogReaderTest extends TestCase
{
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string OIL = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string UNKNOWN = '0193a5e0-7b1c-7a3e-9f00-0000000000ff';
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';
    private const string BOWL = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';

    public function testProductsReturnsOnlyKnownIdsWhateverTheOrderAskedFor(): void
    {
        $reader = (new InMemoryCatalogReader())
            ->withProduct(self::RICE, 'Rice')
            ->withProduct(self::OIL, 'Olive oil', MeasurementCategory::Volume);

        $found = $reader->products([new ProductId(self::UNKNOWN), new ProductId(self::OIL), new ProductId(self::RICE)]);

        $names = array_map(static fn (ProductInfo $p): string => $p->name, $found);
        sort($names);
        self::assertSame(['Olive oil', 'Rice'], $names);
    }

    public function testDishCompositionKeepsProductAndComponentLinesApart(): void
    {
        $reader = (new InMemoryCatalogReader())->withDish(self::BOWL, [self::RICE => Quantity::of('150', Unit::Gram)], [self::SAUCE => '0.5']);

        $found = $reader->dishCompositions([new DishId(self::BOWL), new DishId(self::UNKNOWN)]);

        self::assertCount(1, $found);
        self::assertInstanceOf(DishComposition::class, $found[0]);
        self::assertSame('150 g', (string) $found[0]->productLines[0]->quantity);
        self::assertSame('0.5 portion', (string) $found[0]->componentLines[0]->quantity);
        self::assertTrue((new ComponentId(self::SAUCE))->equals($found[0]->componentLines[0]->componentId));
    }

    public function testComponentBomsCarriesNameAndYieldAndRecordsEveryCall(): void
    {
        $reader = (new InMemoryCatalogReader())->withComponent(self::SAUCE, 'Tomato sauce', 4, [self::OIL => Quantity::of('20', Unit::Millilitre)]);

        $found = $reader->componentBoms([new ComponentId(self::SAUCE)]);
        $reader->componentBoms([new ComponentId(self::UNKNOWN)]);

        self::assertInstanceOf(ComponentBom::class, $found[0]);
        self::assertSame(['Tomato sauce', 4], [$found[0]->name, $found[0]->yieldPortions]);
        self::assertSame(['componentBoms', 'componentBoms'], $reader->calls());
    }
}
