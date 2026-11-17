<?php

declare(strict_types=1);

namespace App\Planning\Domain\Catalog;

use App\Shared\Domain\Identity\DishId;

/**
 * What one portion of a dish is made of. The name is left out on purpose:
 * the decomposer reports products and components, never dishes.
 */
final readonly class DishComposition
{
    /**
     * @param list<ProductLine>   $productLines
     * @param list<ComponentLine> $componentLines
     */
    public function __construct(
        public DishId $dishId,
        public array $productLines,
        public array $componentLines,
    ) {
    }
}
