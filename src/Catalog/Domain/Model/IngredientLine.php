<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Measurement\Quantity;

/**
 * Input for a recipe line: what is used and how much of it.
 *
 * Callers hand lines to an aggregate in this form and the aggregate turns
 * them into its own child entities. That keeps the child entities' identity
 * (owner and position) under the aggregate's control: nobody outside can
 * build a ComponentIngredient for the wrong component or at the wrong
 * position.
 */
final readonly class IngredientLine
{
    public function __construct(
        public IngredientRef $ref,
        public Quantity $quantity,
    ) {
    }
}
