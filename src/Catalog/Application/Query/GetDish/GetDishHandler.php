<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetDish;

use App\Catalog\Application\View\DishView;
use App\Catalog\Application\View\IngredientLineView;
use App\Catalog\Domain\Model\RefKind;
use App\Catalog\Domain\Repository\ComponentRepository;
use App\Catalog\Domain\Repository\DishRepository;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\View\QuantityView;
use App\Shared\Domain\Exception\NotFoundException;

final readonly class GetDishHandler implements QueryHandler
{
    public function __construct(
        private DishRepository $dishes,
        private ProductRepository $products,
        private ComponentRepository $components,
    ) {
    }

    /**
     * The names are fetched in one findByIds() call per kind, whatever the
     * number of lines: one call per line would cost a query per ingredient
     * once the repositories hit the database (the N+1 problem).
     *
     * A line whose ingredient is missing is a broken catalog, not a request
     * for something that does not exist: recipes are checked on creation
     * (ING-01), nothing is deleted, and the foreign keys keep it that way.
     * Hence a LogicException (a 500) rather than a NotFoundException (a 404
     * the client could not act on).
     *
     * @throws NotFoundException when the dish does not exist
     */
    public function __invoke(GetDish $query): DishView
    {
        $dish = $this->dishes->get($query->dishId);

        $productIds = [];
        $componentIds = [];
        foreach ($dish->ingredients() as $line) {
            if ($line->ref()->isProduct()) {
                $productIds[] = $line->ref()->productId();
            } else {
                $componentIds[] = $line->ref()->componentId();
            }
        }

        // A kind the dish does not use is not asked for at all. Keys carry
        // the kind, so a product and a component never shadow each other.
        $names = [];
        if ([] !== $productIds) {
            foreach ($this->products->findByIds($productIds) as $product) {
                $names[RefKind::Product->value.':'.$product->id()] = $product->name();
            }
        }
        if ([] !== $componentIds) {
            foreach ($this->components->findByIds($componentIds) as $component) {
                $names[RefKind::Component->value.':'.$component->id()] = $component->name();
            }
        }

        $lines = [];
        foreach ($dish->ingredients() as $line) {
            $ref = $line->ref();
            $refId = $ref->isProduct() ? $ref->productId()->value : $ref->componentId()->value;
            $key = $ref->kind()->value.':'.$refId;
            $refName = $names[$key] ?? throw new \LogicException(\sprintf('Dish %s uses %s %s, which is not in the catalog.', $dish->id(), $ref->kind()->value, $refId));

            $lines[] = new IngredientLineView($ref->kind()->value, $refId, $refName, QuantityView::of($line->quantity()));
        }

        return new DishView($dish->id()->value, $dish->name(), $lines);
    }
}
