<?php

declare(strict_types=1);

namespace App\Planning\Application\Command\GenerateShoppingList;

use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ShoppingListId;
use App\Shared\Application\Bus\Command;

/**
 * Creates the plan's shopping list, or regenerates it if there is one.
 *
 * A plan has at most one list (SHL-01), so this is create-or-replace:
 * newListId is the id of a list created now, and an existing list keeps its
 * own id, leaving newListId unused. As with AddMealPlanEntry, nothing is
 * returned; a caller that must tell the two cases apart (201 or 200 in
 * D-07) reads the list by plan after dispatch and compares its id.
 */
final readonly class GenerateShoppingList implements Command
{
    public function __construct(
        public MealPlanId $planId,
        public ShoppingListId $newListId,
    ) {
    }
}
