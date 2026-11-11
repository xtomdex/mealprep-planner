<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Exception\ValidationException;

/**
 * The naming rule shared by products, components and dishes: non-empty after
 * trimming, at most MAX_LENGTH characters.
 *
 * A helper that takes the invariant code, not a Name value object: each
 * aggregate owns its own code (PRD-01, CMP-01, DSH-01), and a value object
 * would either throw one generic code for all three or need the code passed
 * in anyway. Passing it here keeps the code visible at the call site, next
 * to the docblock that documents it, and the rule itself in one place.
 *
 * @internal used by the catalog aggregates only
 */
final class CatalogName
{
    public const int MAX_LENGTH = 120;

    private function __construct()
    {
    }

    /**
     * Returns the trimmed name, which is what gets stored: leading and
     * trailing spaces are typing noise and would make "Rice" and "Rice "
     * two different names.
     *
     * @throws ValidationException with the given code, field "name"
     */
    public static function normalize(string $name, string $code): string
    {
        $trimmed = trim($name);

        if ('' === $trimmed) {
            throw new ValidationException($code, 'Name must not be empty.', 'name');
        }

        // Characters, not bytes: "Crème brûlée" is 12 long, not 14.
        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new ValidationException($code, \sprintf('Name must be at most %d characters long.', self::MAX_LENGTH), 'name');
        }

        return $trimmed;
    }
}
