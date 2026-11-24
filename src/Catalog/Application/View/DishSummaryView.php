<?php

declare(strict_types=1);

namespace App\Catalog\Application\View;

use App\Catalog\Domain\Model\Dish;

/**
 * A dish in a list, summarised like ComponentSummaryView.
 */
final readonly class DishSummaryView
{
    public function __construct(
        public string $id,
        public string $name,
        public int $lineCount,
    ) {
    }

    public static function of(Dish $dish): self
    {
        return new self($dish->id()->value, $dish->name(), \count($dish->ingredients()));
    }
}
