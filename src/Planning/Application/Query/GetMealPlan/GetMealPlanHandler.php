<?php

declare(strict_types=1);

namespace App\Planning\Application\Query\GetMealPlan;

use App\Planning\Application\View\MealPlanView;
use App\Planning\Domain\Repository\MealPlanRepository;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Domain\Exception\NotFoundException;

final readonly class GetMealPlanHandler implements QueryHandler
{
    public function __construct(
        private MealPlanRepository $plans,
    ) {
    }

    /**
     * Entries carry the dish id, not its name. Planning reads the catalog
     * only through CatalogReader, which answers for compositions, not for
     * display; and the HTTP layer needs this view to read an entry back
     * after AddMealPlanEntry (201 or 200 in D-07), where the id is enough.
     * A client that shows names already has the dish list.
     *
     * @throws NotFoundException when the plan does not exist
     */
    public function __invoke(GetMealPlan $query): MealPlanView
    {
        return MealPlanView::of($this->plans->get($query->planId));
    }
}
