<?php

declare(strict_types=1);

namespace App\Planning\Application\Query\ListMealPlans;

use App\Planning\Application\View\MealPlanSummaryView;
use App\Planning\Domain\Repository\MealPlanRepository;
use App\Shared\Application\Bus\QueryHandler;

final readonly class ListMealPlansHandler implements QueryHandler
{
    public function __construct(
        private MealPlanRepository $plans,
    ) {
    }

    /**
     * Newest period first: the plan a user opens is almost always the
     * coming week, not one from last month. Two plans may start on the same
     * day (nothing forbids it), so ties fall back to the id and the order
     * never depends on how the repository happened to return them. Days are
     * 'Y-m-d' strings, which sort as dates.
     *
     * @return list<MealPlanSummaryView>
     */
    public function __invoke(ListMealPlans $query): array
    {
        $views = array_map(MealPlanSummaryView::of(...), $this->plans->all());

        usort($views, static function (MealPlanSummaryView $a, MealPlanSummaryView $b): int {
            $byStart = strcmp($b->periodStart, $a->periodStart);

            return 0 !== $byStart ? $byStart : strcmp($a->id, $b->id);
        });

        return $views;
    }
}
