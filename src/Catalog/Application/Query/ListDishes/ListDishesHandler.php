<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\ListDishes;

use App\Catalog\Application\Query\CatalogOrder;
use App\Catalog\Application\View\DishSummaryView;
use App\Catalog\Domain\Repository\DishRepository;
use App\Shared\Application\Bus\QueryHandler;

final readonly class ListDishesHandler implements QueryHandler
{
    public function __construct(
        private DishRepository $dishes,
    ) {
    }

    /**
     * @return list<DishSummaryView>
     */
    public function __invoke(ListDishes $query): array
    {
        return CatalogOrder::byName(array_map(DishSummaryView::of(...), $this->dishes->all()));
    }
}
