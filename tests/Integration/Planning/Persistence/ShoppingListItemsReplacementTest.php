<?php

declare(strict_types=1);

namespace App\Tests\Integration\Planning\Persistence;

use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ProductRequirement;
use App\Planning\Domain\Model\ShoppingList;
use App\Planning\Domain\Model\ShoppingListId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Persistence\MappingTestCase;

/**
 * ShoppingListItemsReplacement: regenerating a list in place and flushing
 * once. The first test is the one that failed in the mapping slice; it is
 * also the guard on ORM upgrades, since the listener relies on an ORM
 * method marked @ignore.
 */
final class ShoppingListItemsReplacementTest extends MappingTestCase
{
    /**
     * replaceItems() clears the collection and adds items at the same
     * positions. Doctrine runs inserts before orphan deletes in one flush,
     * so this is where a (list, position) key could be inserted twice.
     */
    public function testReplacingTheItemsInOneFlushKeepsTheListAndStoresOnlyTheNewItems(): void
    {
        $planId = $this->plan();
        $id = new ShoppingListId(self::nextId());
        $this->em()->persist(ShoppingList::generate($id, $planId, [
            $this->requirement('Rice', '450', Unit::Gram),
            $this->requirement('Tomato', '338', Unit::Gram),
        ], new \DateTimeImmutable('2026-11-25 10:00:00')));
        $this->flushAndClear();

        $list = $this->em()->find(ShoppingList::class, $id);
        self::assertNotNull($list);
        $list->replaceItems([
            $this->requirement('Olive oil', '15', Unit::Millilitre),
            $this->requirement('Rice', '0.9', Unit::Kilogram),
            $this->requirement('Eggs', '6', Unit::Piece),
        ], new \DateTimeImmutable('2026-11-25 11:00:00.000001'));
        $this->flushAndClear();

        $loaded = $this->em()->find(ShoppingList::class, $id);
        self::assertNotNull($loaded);
        self::assertSame('2026-11-25 11:00:00.000001', $loaded->generatedAt()->format('Y-m-d H:i:s.u'));
        self::assertSame(
            [[1, 'Olive oil', '15 ml'], [2, 'Rice', '900 g'], [3, 'Eggs', '6 pcs']],
            array_map(static fn ($item): array => [$item->position(), $item->productName(), (string) $item->quantity()], $loaded->items()),
        );
        self::assertSame(3, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM planning_shopping_list_items WHERE shopping_list_id = ?', [$id->value]));
    }

    public function testReplacingWithFewerItemsLeavesNoStrayRows(): void
    {
        $id = $this->storedList([$this->requirement('Rice', '450', Unit::Gram), $this->requirement('Tomato', '338', Unit::Gram), $this->requirement('Eggs', '6', Unit::Piece)]);

        $this->em()->find(ShoppingList::class, $id)?->replaceItems([$this->requirement('Rice', '200', Unit::Gram)], new \DateTimeImmutable());
        $this->flushAndClear();

        self::assertSame(
            [[1, 'Rice', '200.000000 g']],
            $this->connection()->fetchAllNumeric('SELECT position, product_name, quantity_amount || \' \' || quantity_unit FROM planning_shopping_list_items WHERE shopping_list_id = ? ORDER BY position', [$id->value]),
        );
    }

    public function testReplacingWithTheSameProductAtTheSamePositionKeepsOneRow(): void
    {
        $rice = new ProductId(self::nextId());
        $id = $this->storedList([new ProductRequirement($rice, 'Rice', Quantity::of('450', Unit::Gram))]);

        $this->em()->find(ShoppingList::class, $id)?->replaceItems([new ProductRequirement($rice, 'Rice', Quantity::of('500', Unit::Gram))], new \DateTimeImmutable());
        $this->flushAndClear();

        self::assertSame(
            [[$rice->value, '500.000000']],
            $this->connection()->fetchAllNumeric('SELECT product_id, quantity_amount FROM planning_shopping_list_items WHERE shopping_list_id = ?', [$id->value]),
        );
    }

    /**
     * @param list<ProductRequirement> $requirements
     */
    private function storedList(array $requirements): ShoppingListId
    {
        $id = new ShoppingListId(self::nextId());
        $this->em()->persist(ShoppingList::generate($id, $this->plan(), $requirements, new \DateTimeImmutable()));
        $this->flushAndClear();

        return $id;
    }

    private function plan(): MealPlanId
    {
        $id = new MealPlanId(self::nextId());
        $this->em()->persist(MealPlan::create($id, new \DateTimeImmutable('2026-11-30'), new \DateTimeImmutable('2026-11-25'), new \DateTimeImmutable()));
        $this->em()->flush();

        return $id;
    }

    private function requirement(string $name, string $amount, Unit $unit): ProductRequirement
    {
        return new ProductRequirement(new ProductId(self::nextId()), $name, Quantity::of($amount, $unit));
    }
}
