<?php

declare(strict_types=1);

namespace App\Planning\Domain\Catalog;

use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;

/**
 * "This much of this product" inside a dish or a component recipe.
 */
final readonly class ProductLine
{
    public function __construct(
        public ProductId $productId,
        public Quantity $quantity,
    ) {
    }
}
