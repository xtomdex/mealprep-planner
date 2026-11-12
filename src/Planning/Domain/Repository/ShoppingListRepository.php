<?php

declare(strict_types=1);

namespace App\Planning\Domain\Repository;

use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ShoppingList;

/**
 * Lists are looked up by plan, not by their own id: a plan has at most one
 * list (SHL-01), and "the list of plan X" is how every caller asks for it.
 */
interface ShoppingListRepository
{
    /**
     * Null when no list was generated for the plan yet; that is a normal
     * state, not an error, so there is no NotFoundException here.
     */
    public function findByPlan(MealPlanId $mealPlanId): ?ShoppingList;

    /**
     * Adding a second list for the same plan breaks SHL-01; the database
     * rejects it with a UNIQUE constraint on the plan id.
     */
    public function add(ShoppingList $list): void;
}
