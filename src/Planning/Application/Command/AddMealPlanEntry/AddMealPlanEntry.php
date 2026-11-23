<?php

declare(strict_types=1);

namespace App\Planning\Application\Command\AddMealPlanEntry;

use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Application\Bus\Command;
use App\Shared\Domain\Identity\DishId;

/**
 * Adds portions of a dish on one day of a plan.
 *
 * newEntryId is the id a new entry gets. When the plan already has an entry
 * for this (day, dish), the portions are added to that one instead (PLN-06)
 * and newEntryId stays unused, so the stored id can differ from it. The
 * command still returns nothing (CQS): (day, dish) is a natural key of an
 * entry, so a caller that must know which case happened (the HTTP layer
 * answering 201 or 200, D-07) reads the plan after dispatch and finds the
 * entry by that pair.
 */
final readonly class AddMealPlanEntry implements Command
{
    public function __construct(
        public MealPlanId $planId,
        public MealPlanEntryId $newEntryId,
        public \DateTimeImmutable $day,
        public DishId $dishId,
        public int $portions,
    ) {
    }
}
