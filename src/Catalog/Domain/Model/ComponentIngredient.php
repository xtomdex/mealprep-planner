<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Measurement\Quantity;

/**
 * One line of a component's recipe. It has no id of its own: it is "line N
 * of component X" and means nothing outside that component, so owner and
 * position together are its identity (a composite key once it is mapped).
 *
 * @internal created only by Component::create(); build lines with IngredientLine
 */
final class ComponentIngredient
{
    /**
     * @param int $position 1-based, in the order the lines were given
     */
    public function __construct(
        private readonly Component $component,
        private readonly int $position,
        private readonly IngredientRef $ref,
        private readonly Quantity $quantity,
    ) {
    }

    public function component(): Component
    {
        return $this->component;
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
