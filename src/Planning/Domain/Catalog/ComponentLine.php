<?php

declare(strict_types=1);

namespace App\Planning\Domain\Catalog;

use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Measurement\Quantity;

/**
 * "This many portions of this component" inside a dish or a parent
 * component. The quantity is always in Unit::Portion (ING-02 for components).
 */
final readonly class ComponentLine
{
    public function __construct(
        public ComponentId $componentId,
        public Quantity $quantity,
    ) {
    }
}
