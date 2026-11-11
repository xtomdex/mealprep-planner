<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Measurement\Quantity;

/**
 * One line of a dish, identified by its dish and position like
 * ComponentIngredient. The two are separate classes rather than one shared
 * "line" type because they belong to different aggregates and will map to
 * different tables with different foreign keys.
 *
 * @internal created only by Dish::create(); build lines with IngredientLine
 */
final class DishIngredient
{
    /**
     * @param int $position 1-based, in the order the lines were given
     */
    public function __construct(
        private readonly Dish $dish,
        private readonly int $position,
        private readonly IngredientRef $ref,
        private readonly Quantity $quantity,
    ) {
    }

    public function dish(): Dish
    {
        return $this->dish;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function ref(): IngredientRef
    {
        return $this->ref;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }
}
