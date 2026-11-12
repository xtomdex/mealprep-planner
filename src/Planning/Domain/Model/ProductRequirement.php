<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;

/**
 * "This much of this product is needed": the input to a shopping list and,
 * once the decomposer exists, its output.
 *
 * The product name travels with the id because Planning may not read the
 * catalog's Product; the list keeps the name it was generated with, which is
 * also what a snapshot should do when a product is renamed later.
 */
final readonly class ProductRequirement
{
    public function __construct(
        public ProductId $productId,
        public string $productName,
        public Quantity $quantity,
    ) {
    }
}
