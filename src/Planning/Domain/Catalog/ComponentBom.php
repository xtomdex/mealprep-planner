<?php

declare(strict_types=1);

namespace App\Planning\Domain\Catalog;

use App\Shared\Domain\Identity\ComponentId;

/**
 * A component's bill of materials: what one run of its recipe uses and how
 * many portions that run yields. Lines are per run, not per portion.
 */
final readonly class ComponentBom
{
    /**
     * @param int                 $yieldPortions  at least one (CMP-03 in the catalog)
     * @param list<ProductLine>   $productLines
     * @param list<ComponentLine> $componentLines
     */
    public function __construct(
        public ComponentId $componentId,
        public string $name,
        public int $yieldPortions,
        public array $productLines,
        public array $componentLines,
    ) {
    }
}
