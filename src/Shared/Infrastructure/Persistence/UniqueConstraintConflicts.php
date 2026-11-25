<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Domain\Exception\ConflictException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Turns the unique violations a client can cause into the domain's conflict,
 * by the constraint's name; every unique constraint in the schema has an
 * explicit, stable one for this purpose.
 *
 * Only the constraints listed here are a client's doing (two products with
 * one name). Any other violation means the code broke an invariant it was
 * supposed to keep, and stays a server error.
 */
final readonly class UniqueConstraintConflicts
{
    /**
     * @var array<string, array{string, string}> constraint name => invariant code, message
     */
    private const array CONFLICTS = [
        'uq_catalog_products_name' => ['PRD-02', 'A product with this name already exists.'],
        'uq_catalog_components_name' => ['CMP-05', 'A component with this name already exists.'],
        'uq_catalog_dishes_name' => ['DSH-04', 'A dish with this name already exists.'],
    ];

    /**
     * PostgreSQL names the constraint in the message ("violates unique
     * constraint "uq_…""); the driver exposes no separate field for it.
     */
    public function conflictFor(UniqueConstraintViolationException $violation): ?ConflictException
    {
        if (1 !== preg_match('/unique constraint "(?<name>[^"]+)"/', $violation->getMessage(), $match)) {
            return null;
        }

        $conflict = self::CONFLICTS[$match['name']] ?? null;

        return null === $conflict ? null : new ConflictException(...$conflict);
    }
}
