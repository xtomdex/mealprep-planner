<?php

declare(strict_types=1);

namespace App\Planning\Application\View;

final readonly class ShoppingListView
{
    /**
     * @param bool                   $stale true when the plan's entries changed after generatedAt
     * @param list<ShoppingItemView> $items by position
     */
    public function __construct(
        public string $id,
        public string $mealPlanId,
        public \DateTimeImmutable $generatedAt,
        public bool $stale,
        public array $items,
    ) {
    }
}
