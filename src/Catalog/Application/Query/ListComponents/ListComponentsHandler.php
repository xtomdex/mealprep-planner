<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\ListComponents;

use App\Catalog\Application\Query\CatalogOrder;
use App\Catalog\Application\View\ComponentSummaryView;
use App\Catalog\Domain\Repository\ComponentRepository;
use App\Shared\Application\Bus\QueryHandler;

final readonly class ListComponentsHandler implements QueryHandler
{
    public function __construct(
        private ComponentRepository $components,
    ) {
    }

    /**
     * @return list<ComponentSummaryView>
     */
    public function __invoke(ListComponents $query): array
    {
        return CatalogOrder::byName(array_map(ComponentSummaryView::of(...), $this->components->all()));
    }
}
