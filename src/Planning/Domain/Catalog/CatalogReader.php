<?php

declare(strict_types=1);

namespace App\Planning\Domain\Catalog;

use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;

/**
 * Planning's window onto the catalog: the port it owns, implemented later by
 * an infrastructure adapter that calls Catalog's application layer.
 *
 * The DTOs it returns are Planning's own anti-corruption layer. Catalog's
 * IngredientRef (a reference plus a kind) may not be imported here, and
 * re-creating it would bring back a runtime check of "which of the two ids
 * is set". Instead every composition carries two lists, productLines and
 * componentLines: a line references exactly one thing because its type says
 * so, not because a constructor checked it.
 *
 * Every method takes a batch of ids and returns only the ones that exist, in
 * no particular order. Batches keep the decomposer at a fixed number of calls
 * per plan (no N+1 behind the port); "only what exists" leaves the decision
 * about a missing id to the caller, which knows whether that is an error.
 */
interface CatalogReader
{
    /**
     * @param list<DishId> $ids
     *
     * @return list<DishComposition>
     */
    public function dishCompositions(array $ids): array;

    /**
     * @param list<ComponentId> $ids
     *
     * @return list<ComponentBom>
     */
    public function componentBoms(array $ids): array;

    /**
     * @param list<ProductId> $ids
     *
     * @return list<ProductInfo>
     */
    public function products(array $ids): array;
}
