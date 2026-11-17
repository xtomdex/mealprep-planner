<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

/**
 * Everything a meal plan needs: products to buy and components to prepare.
 *
 * Exactly these two fields, and the contract is not meant to grow: anything
 * else a caller wants (costs, days, per-dish breakdowns) is a different
 * question and gets its own result type instead of optional fields here.
 *
 * The lists are kept as given; ordering them is the producer's job, so this
 * class stays a plain carrier with nothing to test beyond its shape.
 */
final readonly class PlanRequirements
{
    /**
     * @param list<ProductRequirement>   $products
     * @param list<ComponentRequirement> $components
     */
    public function __construct(
        public array $products,
        public array $components,
    ) {
    }
}
