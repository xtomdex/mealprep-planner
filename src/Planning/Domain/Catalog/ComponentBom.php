<?php

declare(strict_types=1);

namespace App\Planning\Domain\Catalog;

use App\Planning\Domain\Exception\InconsistentCatalogData;
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
     *
     * @throws InconsistentCatalogData when the yield is below one portion
     */
    public function __construct(
        public ComponentId $componentId,
        public string $name,
        public int $yieldPortions,
        public array $productLines,
        public array $componentLines,
    ) {
        // Checked here, where the adapter builds the DTO, so a zero yield
        // never reaches a division and is reported with the component's id.
        if ($yieldPortions < 1) {
            throw InconsistentCatalogData::nonPositiveYield($componentId, $yieldPortions);
        }
    }
}
