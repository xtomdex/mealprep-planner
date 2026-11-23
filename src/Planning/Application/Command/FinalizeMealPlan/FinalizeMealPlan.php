<?php

declare(strict_types=1);

namespace App\Planning\Application\Command\FinalizeMealPlan;

use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Application\Bus\Command;

/**
 * Freezes a plan: no entry can be added or removed afterwards.
 */
final readonly class FinalizeMealPlan implements Command
{
    public function __construct(
        public MealPlanId $planId,
    ) {
    }
}
