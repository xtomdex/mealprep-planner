<?php

declare(strict_types=1);

namespace App\Planning\Domain\Exception;

use App\Shared\Domain\Identity\Uuid;

/**
 * Something the plan refers to, directly or through a recipe, is not in the
 * catalog. The message names the id so the broken reference can be found.
 */
final class MissingCatalogData extends CatalogIntegrityViolation
{
    /**
     * @param string $kind what was looked up: "dish", "component" or "product"
     */
    public static function for(string $kind, Uuid $id): self
    {
        return new self(\sprintf('The catalog has no %s %s, but the meal plan needs it.', $kind, $id));
    }
}
