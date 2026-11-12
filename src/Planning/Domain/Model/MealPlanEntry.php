<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

use App\Shared\Domain\Identity\DishId;

/**
 * "N portions of this dish on this day" inside one plan.
 *
 * @internal created and changed only by MealPlan; use MealPlan::addEntry()
 */
final class MealPlanEntry
{
    public function __construct(
        private readonly MealPlan $mealPlan,
        private readonly MealPlanEntryId $id,
        private readonly \DateTimeImmutable $day,
        private readonly DishId $dishId,
        private int $portions,
    ) {
    }

    public function mealPlan(): MealPlan
    {
        return $this->mealPlan;
    }

    public function id(): MealPlanEntryId
    {
        return $this->id;
    }

    /**
     * Midnight of the day, as normalised by MealPlan.
     */
    public function day(): \DateTimeImmutable
    {
        return $this->day;
    }

    public function dishId(): DishId
    {
        return $this->dishId;
    }

    public function portions(): int
    {
        return $this->portions;
    }

    /**
     * @internal the plan has already checked that $portions is at least one
     */
    public function addPortions(int $portions): void
    {
        $this->portions += $portions;
    }
}
