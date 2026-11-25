<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Persistence;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\Product;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Persistence\MappingTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;

final class DishMappingTest extends MappingTestCase
{
    public function testADishRoundTripsWithItsLinesInPositionOrder(): void
    {
        $oil = $this->product();
        $rice = new ComponentId(self::nextId());
        $this->em()->persist(Component::create($rice, 'Cooked rice', 4, [
            new IngredientLine(IngredientRef::product($oil), Quantity::of('5', Unit::Millilitre)),
        ], new \DateTimeImmutable()));
        $this->em()->flush();

        $id = new DishId(self::nextId());
        $this->em()->persist(Dish::create($id, 'Rice bowl', [
            new IngredientLine(IngredientRef::component($rice), Quantity::of('1', Unit::Portion)),
            new IngredientLine(IngredientRef::product($oil), Quantity::of('7.5', Unit::Millilitre)),
        ], new \DateTimeImmutable('2026-11-25 12:00:00.000001')));
        $this->flushAndClear();

        $loaded = $this->em()->find(Dish::class, $id);

        self::assertNotNull($loaded);
        self::assertSame('Rice bowl', $loaded->name());
        self::assertSame('2026-11-25 12:00:00.000001', $loaded->createdAt()->format('Y-m-d H:i:s.u'));
        [$first, $second] = $loaded->ingredients();
        self::assertSame(1, $first->position());
        self::assertTrue($first->ref()->componentId()->equals($rice));
        self::assertSame('1 portion', (string) $first->quantity());
        self::assertSame(2, $second->position());
        self::assertTrue($second->ref()->productId()->equals($oil));
        self::assertSame('7.5 ml', (string) $second->quantity());
    }

    public function testTheCheckRefusesALineWithNeitherId(): void
    {
        $id = new DishId(self::nextId());
        $this->em()->persist(Dish::create($id, 'Plain bowl', [
            new IngredientLine(IngredientRef::product($this->product()), Quantity::of('1', Unit::Millilitre)),
        ], new \DateTimeImmutable()));
        $this->em()->flush();

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_catalog_dish_ingredients_one_ref');

        $this->connection()->insert('catalog_dish_ingredients', [
            'dish_id' => $id->value,
            'position' => 2,
            'ref_kind' => 'product',
            'quantity_amount' => '1',
            'quantity_unit' => 'ml',
        ]);
    }

    /**
     * Exactly one id is set in both cases, so ck_..._one_ref passes and only
     * the kind check can refuse the row.
     *
     * @return iterable<string, array{string, bool}> kind, whether the product id (not the component id) is set
     */
    public static function mismatchedKinds(): iterable
    {
        yield 'kind product, only a component id' => ['product', false];
        yield 'kind component, only a product id' => ['component', true];
    }

    /**
     * Hydration would turn such a row into an IngredientRef whose kind and
     * id disagree; the constraint keeps it out of the table.
     */
    #[DataProvider('mismatchedKinds')]
    public function testTheCheckRefusesALineWhoseKindDoesNotMatchItsId(string $kind, bool $productSet): void
    {
        $oil = $this->product();
        $rice = new ComponentId(self::nextId());
        $this->em()->persist(Component::create($rice, 'Cooked rice', 4, [
            new IngredientLine(IngredientRef::product($oil), Quantity::of('5', Unit::Millilitre)),
        ], new \DateTimeImmutable()));
        $id = new DishId(self::nextId());
        $this->em()->persist(Dish::create($id, 'Plain bowl', [
            new IngredientLine(IngredientRef::product($oil), Quantity::of('1', Unit::Millilitre)),
        ], new \DateTimeImmutable()));
        $this->em()->flush();

        // Not the oil: it is on line 1 already, and the unique index would answer first.
        $vinegar = $this->product('Vinegar');

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_catalog_dish_ingredients_ref_kind_matches');

        $this->connection()->insert('catalog_dish_ingredients', [
            'dish_id' => $id->value,
            'position' => 2,
            'ref_kind' => $kind,
            'product_id' => $productSet ? $vinegar->value : null,
            'child_component_id' => $productSet ? null : $rice->value,
            'quantity_amount' => '1',
            'quantity_unit' => 'ml',
        ]);
    }

    public function testALinePointingAtAMissingComponentIsRefusedByItsForeignKey(): void
    {
        $this->em()->persist(Dish::create(new DishId(self::nextId()), 'Ghost bowl', [
            new IngredientLine(IngredientRef::component(new ComponentId(self::nextId())), Quantity::of('1', Unit::Portion)),
        ], new \DateTimeImmutable()));

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->expectExceptionMessage('fk_catalog_dish_ingredients_child_component_id');

        $this->em()->flush();
    }

    private function product(string $name = 'Olive oil'): ProductId
    {
        $id = new ProductId(self::nextId());
        $this->em()->persist(Product::create($id, $name, MeasurementCategory::Volume, new \DateTimeImmutable()));
        $this->em()->flush();

        return $id;
    }
}
