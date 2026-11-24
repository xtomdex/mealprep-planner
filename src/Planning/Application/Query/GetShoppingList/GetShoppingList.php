<?php

declare(strict_types=1);

namespace App\Planning\Application\Query\GetShoppingList;

use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Application\Bus\Query;

/**
 * The shopping list of a plan, addressed by the plan as everywhere else
 * (see ShoppingListRepository).
 */
final readonly class GetShoppingList implements Query
{
    public function __construct(
        public MealPlanId $planId,
    ) {
    }
}
