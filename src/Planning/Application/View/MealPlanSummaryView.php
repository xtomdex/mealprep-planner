<?php

declare(strict_types=1);

namespace App\Planning\Application\View;

use App\Planning\Domain\Model\MealPlan;

/**
 * A plan in a list: its period and state, with a count instead of the entries.
 */
final readonly class MealPlanSummaryView
{
    /**
     * @param string $periodStart first day, Y-m-d
     * @param string $periodEnd   seventh day, Y-m-d, inclusive
     * @param string $status      draft or finalized
     */
    public function __construct(
        public string $id,
        public string $periodStart,
        public string $periodEnd,
        public string $status,
        public int $entryCount,
    ) {
    }

    public static function of(MealPlan $plan): self
    {
        return new self(
            $plan->id()->value,
            $plan->periodStart()->format('Y-m-d'),
            $plan->periodEnd()->format('Y-m-d'),
            $plan->status()->value,
            \count($plan->entries()),
        );
    }
}
