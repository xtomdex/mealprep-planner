<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;

/**
 * "This many portions of this component are needed": what the kitchen has to
 * prepare, next to the products the shopping list buys.
 *
 * Portions are a Quantity, not an int: a dish may use half a portion of a
 * sauce, and a component cooked for a parent one gets a share of the parent's
 * yield, so the need is often fractional. Rounding it is the caller's choice.
 */
final readonly class ComponentRequirement
{
    /**
     * @throws \InvalidArgumentException when portions are not in Unit::Portion — a bug in the caller, not input to validate
     */
    public function __construct(
        public ComponentId $componentId,
        public string $componentName,
        public Quantity $portions,
    ) {
        if (Unit::Portion !== $portions->unit) {
            throw new \InvalidArgumentException(\sprintf('Component %s must be measured in portions, got %s.', $componentId, $portions));
        }
    }
}
