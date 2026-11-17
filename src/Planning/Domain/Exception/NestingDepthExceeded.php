<?php

declare(strict_types=1);

namespace App\Planning\Domain\Exception;

use App\Shared\Domain\Identity\ComponentId;

/**
 * A component used inside another component has components of its own.
 *
 * The catalog refuses to save such a recipe (ING-04: two levels at most), so
 * this is reachable only with corrupted data. The decomposer checks anyway:
 * silently ignoring the third level would produce a shopping list that is
 * short of products, which is worse than failing loudly.
 */
final class NestingDepthExceeded extends CatalogIntegrityViolation
{
    public static function at(ComponentId $child, ComponentId $parent): self
    {
        return new self(\sprintf('Component %s is used inside component %s and has components of its own; recipes nest at most two levels deep.', $child, $parent));
    }
}
