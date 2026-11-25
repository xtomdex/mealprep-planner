<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Persistence;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\Product;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Persistence\MappingTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;

final class ComponentMappingTest extends MappingTestCase
{
    public function testAComponentRoundTripsWithItsLinesInPositionOrderAndShortAmounts(): void
    {
        $tomato = $this->product('Tomato');
        $spices = $this->component('Spice mix', [new IngredientLine(IngredientRef::product($tomato), Quantity::of('10', Unit::Gram))]);
        $id = new ComponentId(self::nextId());
        $createdAt = new \DateTimeImmutable('2026-11-25 10:15:30.654321');
        $this->em()->persist(Component::create($id, 'Tomato sauce', 4, [
            new IngredientLine(IngredientRef::product($tomato), Quantity::of('150', Unit::Gram)),
            new IngredientLine(IngredientRef::component($spices), Quantity::of('0.5', Unit::Portion)),
        ], $createdAt));
        $this->flushAndClear();

        $loaded = $this->em()->find(Component::class, $id);

        self::assertNotNull($loaded);
        self::assertSame('Tomato sauce', $loaded->name());
        self::assertSame(4, $loaded->yieldPortions());
        self::assertSame($createdAt->format('U.u'), $loaded->createdAt()->format('U.u'));
        self::assertSame($createdAt->format('U.u'), $loaded->updatedAt()->format('U.u'));

        [$first, $second] = $loaded->ingredients();
        self::assertSame(1, $first->position());
        self::assertTrue($first->ref()->productId()->equals($tomato));
        self::assertSame('150', $first->quantity()->amount);
        self::assertSame(Unit::Gram, $first->quantity()->unit);
        self::assertSame(2, $second->position());
        self::assertTrue($second->ref()->componentId()->equals($spices));
        self::assertSame('0.5', $second->quantity()->amount);
        self::assertSame(Unit::Portion, $second->quantity()->unit);
    }

    /**
     * Pins that the short amount above is the type's work: the column itself
     * is padded to the declared scale.
     */
    public function testTheDatabaseItselfPadsTheAmount(): void
    {
        $tomato = $this->product('Tomato');
        $spices = $this->component('Spice mix', [new IngredientLine(IngredientRef::product($tomato), Quantity::of('150', Unit::Gram))]);

        $stored = $this->connection()->fetchOne('SELECT quantity_amount FROM catalog_component_ingredients WHERE component_id = ?', [$spices->value]);

        self::assertSame('150.000000', $stored);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function invalidReferences(): iterable
    {
        yield 'both ids' => [true];
        yield 'neither id' => [false];
    }

    /**
     * IngredientRef cannot be built in either state, so the row is written
     * with SQL: the constraint is the last line of defence for code that
     * bypasses the domain.
     */
    #[DataProvider('invalidReferences')]
    public function testTheCheckRefusesALineWithBothIdsOrNeither(bool $both): void
    {
        $tomato = $this->product('Tomato');
        $owner = $this->component('Spice mix', [new IngredientLine(IngredientRef::product($tomato), Quantity::of('10', Unit::Gram))]);
        $child = $this->component('Salt mix', [new IngredientLine(IngredientRef::product($tomato), Quantity::of('5', Unit::Gram))]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_catalog_component_ingredients_one_ref');

        $this->connection()->insert('catalog_component_ingredients', [
            'component_id' => $owner->value,
            'position' => 2,
            'ref_kind' => 'product',
            'product_id' => $both ? $tomato->value : null,
            'child_component_id' => $both ? $child->value : null,
            'quantity_amount' => '1',
            'quantity_unit' => 'g',
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
        $tomato = $this->product('Tomato');
        $owner = $this->component('Spice mix', [new IngredientLine(IngredientRef::product($tomato), Quantity::of('10', Unit::Gram))]);
        $child = $this->component('Salt mix', [new IngredientLine(IngredientRef::product($tomato), Quantity::of('5', Unit::Gram))]);

        // Not Tomato: it is on line 1 already, and the unique index would answer first.
        $salt = $this->product('Salt');

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_catalog_component_ingredients_ref_kind_matches');

        $this->connection()->insert('catalog_component_ingredients', [
            'component_id' => $owner->value,
            'position' => 2,
            'ref_kind' => $kind,
            'product_id' => $productSet ? $salt->value : null,
            'child_component_id' => $productSet ? null : $child->value,
            'quantity_amount' => '1',
            'quantity_unit' => 'g',
        ]);
    }

    public function testALinePointingAtAMissingProductIsRefusedByItsForeignKey(): void
    {
        $this->em()->persist(Component::create(new ComponentId(self::nextId()), 'Ghost sauce', 1, [
            new IngredientLine(IngredientRef::product(new ProductId(self::nextId())), Quantity::of('10', Unit::Gram)),
        ], new \DateTimeImmutable()));

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->expectExceptionMessage('fk_catalog_component_ingredients_product_id');

        $this->em()->flush();
    }

    private function product(string $name): ProductId
    {
        $id = new ProductId(self::nextId());
        $this->em()->persist(Product::create($id, $name, MeasurementCategory::Weight, new \DateTimeImmutable()));
        $this->em()->flush();

        return $id;
    }

    /**
     * Flushed on its own: the foreign keys are checked per statement, and
     * Doctrine orders inserts by associations, which ids are not.
     *
     * @param list<IngredientLine> $lines
     */
    private function component(string $name, array $lines): ComponentId
    {
        $id = new ComponentId(self::nextId());
        $this->em()->persist(Component::create($id, $name, 1, $lines, new \DateTimeImmutable()));
        $this->em()->flush();

        return $id;
    }
}
