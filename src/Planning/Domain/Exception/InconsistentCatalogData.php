<?php

declare(strict_types=1);

namespace App\Planning\Domain\Exception;

use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;

/**
 * The catalog returned data that breaks one of its own rules. Each case gets
 * a named constructor that names the ids, so the broken record can be found
 * without a debugger. Without these guards the same data would surface as a
 * DivisionByZeroError or as a ValidationException (QTY-02) — the first hides
 * the cause, the second blames the client for a server-side problem.
 */
final class InconsistentCatalogData extends CatalogIntegrityViolation
{
    /**
     * CMP-03 in the catalog: a recipe yields at least one portion.
     */
    public static function nonPositiveYield(ComponentId $componentId, int $yieldPortions): self
    {
        return new self(\sprintf('Component %s yields %d portions; a recipe must yield at least one.', $componentId, $yieldPortions));
    }

    /**
     * ING-02 in the catalog: a product line is measured in the product's category.
     */
    public static function categoryMismatch(ProductId $productId, MeasurementCategory $expected, MeasurementCategory $found): self
    {
        return new self(\sprintf('Product %s is measured by %s, but a recipe line uses %s.', $productId, $expected->value, $found->value));
    }
}
