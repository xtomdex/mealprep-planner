<?php

declare(strict_types=1);

namespace App\Tests\Support\Planning;

use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ShoppingList;
use App\Planning\Domain\Repository\ShoppingListRepository;

/**
 * A working ShoppingListRepository backed by an array keyed by plan id.
 *
 * add() refuses a second list for the same plan, the way the UNIQUE
 * constraint will (SHL-01); a fake that silently overwrote it would let a
 * handler that forgets findByPlan() pass its tests.
 */
final class InMemoryShoppingListRepository implements ShoppingListRepository
{
    /** @var array<string, ShoppingList> keyed by meal plan id value */
    private array $lists = [];

    public function findByPlan(MealPlanId $mealPlanId): ?ShoppingList
    {
        return $this->lists[$mealPlanId->value] ?? null;
    }

    public function add(ShoppingList $list): void
    {
        $key = $list->mealPlanId()->value;
        if (isset($this->lists[$key]) && $this->lists[$key] !== $list) {
            throw new \LogicException(\sprintf('Meal plan %s already has a shopping list (SHL-01).', $key));
        }

        $this->lists[$key] = $list;
    }
}
