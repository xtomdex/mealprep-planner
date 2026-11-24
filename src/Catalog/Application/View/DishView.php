<?php

declare(strict_types=1);

namespace App\Catalog\Application\View;

final readonly class DishView
{
    /**
     * @param list<IngredientLineView> $lines in recipe order
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $lines,
    ) {
    }
}
