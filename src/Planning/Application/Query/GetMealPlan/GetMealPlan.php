<?php

declare(strict_types=1);

namespace App\Planning\Application\Query\GetMealPlan;

use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Application\Bus\Query;

/**
 * One plan with all its entries.
 */
final readonly class GetMealPlan implements Query
{
    public function __construct(
        public MealPlanId $planId,
    ) {
    }
}
