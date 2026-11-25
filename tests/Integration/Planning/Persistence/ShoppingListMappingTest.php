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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class ShoppingListMappingTest extends MappingTestCase
{
    /**
     * The products are made-up ids that are not in the catalog: an item has
     * no foreign key, because the list is a snapshot and keeps the name.
     */
    public function testAGeneratedListRoundTripsWithItsItemsInPositionOrder(): void
    {
        $planId = $this->plan();
        $id = new ShoppingListId(self::nextId());
        $generatedAt = new \DateTimeImmutable('2026-11-25 10:00:00.123456');
        $this->em()->persist(ShoppingList::generate($id, $planId, [
            $this->requirement('Tomato', '337.5', Unit::Gram),
            $this->requirement('Rice', '0.45', Unit::Kilogram),
            $this->requirement('Olive oil', '15', Unit::Millilitre),
        ], $generatedAt));
        $this->flushAndClear();

        $loaded = $this->em()->find(ShoppingList::class, $id);

        self::assertNotNull($loaded);
        self::assertTrue($loaded->mealPlanId()->equals($planId));
        self::assertSame($generatedAt->format('U.u'), $loaded->generatedAt()->format('U.u'));
        self::assertSame(
            [[1, 'Tomato', '338 g'], [2, 'Rice', '450 g'], [3, 'Olive oil', '15 ml']],
            array_map(static fn ($item): array => [$item->position(), $item->productName(), (string) $item->quantity()], $loaded->items()),
        );
    }

    /**
     * SHL-01 is not checked by the aggregate; the named unique index is
     * what the next slice turns into a 409.
     */
    public function testASecondListForTheSamePlanIsRefusedByItsNamedConstraint(): void
    {
        $planId = $this->plan();
        $this->em()->persist(ShoppingList::generate(new ShoppingListId(self::nextId()), $planId, [$this->requirement('Rice', '1', Unit::Gram)], new \DateTimeImmutable()));
        $this->em()->flush();

        $this->em()->persist(ShoppingList::generate(new ShoppingListId(self::nextId()), $planId, [$this->requirement('Rice', '1', Unit::Gram)], new \DateTimeImmutable()));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->expectExceptionMessage('uq_planning_shopping_lists_meal_plan_id');

        $this->em()->flush();
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
