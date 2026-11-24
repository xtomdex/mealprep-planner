<?php

declare(strict_types=1);

namespace App\Planning\Application\View;

use App\Shared\Application\View\QuantityView;

/**
 * One thing to buy. The quantity is canonical and whole (g, ml, pcs),
 * as the list stores it.
 */
final readonly class ShoppingItemView
{
    /**
     * @param int $position 1-based
     */
    public function __construct(
        public string $productId,
        public string $productName,
        public QuantityView $quantity,
        public int $position,
    ) {
    }
}
