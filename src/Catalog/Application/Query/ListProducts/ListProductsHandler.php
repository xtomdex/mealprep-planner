<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\ListProducts;

use App\Catalog\Application\Query\CatalogOrder;
use App\Catalog\Application\View\ProductView;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Application\Bus\QueryHandler;

final readonly class ListProductsHandler implements QueryHandler
{
    public function __construct(
        private ProductRepository $products,
    ) {
    }

    /**
     * @return list<ProductView>
     */
    public function __invoke(ListProducts $query): array
    {
        return CatalogOrder::byName(array_map(ProductView::of(...), $this->products->all()));
    }
}
