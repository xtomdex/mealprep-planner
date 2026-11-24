<?php

declare(strict_types=1);

namespace App\Planning\Application\View;

use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntry;

/**
 * A plan with its entries. Days are Y-m-d strings and finalizedAt is a
 * moment, as described in QueryHandler.
 */
final readonly class MealPlanView
{
    /**
     * @param string                  $status      draft or finalized
     * @param \DateTimeImmutable|null $finalizedAt null while the plan is a draft
     * @param list<MealPlanEntryView> $entries     by day, then in the order they were added
     */
    public function __construct(
        public string $id,
        public string $periodStart,
        public string $periodEnd,
        public string $status,
        public ?\DateTimeImmutable $finalizedAt,
        public array $entries,
    ) {
    }

    /**
     * The order comes from MealPlan::entries(), which owns it.
     */
    public static function of(MealPlan $plan): self
    {
        return new self(
            $plan->id()->value,
            $plan->periodStart()->format('Y-m-d'),
            $plan->periodEnd()->format('Y-m-d'),
            $plan->status()->value,
            $plan->finalizedAt(),
            array_map(
                static fn (MealPlanEntry $entry): MealPlanEntryView => new MealPlanEntryView($entry->id()->value, $entry->day()->format('Y-m-d'), $entry->dishId()->value, $entry->portions()),
                $plan->entries(),
            ),
        );
    }
}
