<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Exception\ValidationException;

/**
 * The rules every list of recipe lines follows, whoever owns it: at least
 * one line, and no ingredient listed twice. Components and dishes share the
 * rules but not the codes, so the codes are passed in, as with CatalogName.
 *
 * @internal used by the catalog aggregates only
 */
final class Composition
{
    private function __construct()
    {
    }

    /**
     * A duplicate is rejected rather than merged: "200 g rice" and "100 g
     * rice" on one recipe is more likely a typo than an intent, and merging
     * would hide it.
     *
     * @param list<IngredientLine> $lines
     *
     * @throws ValidationException $emptyCode or $duplicateCode, field "ingredients"
     */
    public static function assertValid(array $lines, string $emptyCode, string $duplicateCode): void
    {
        if ([] === $lines) {
            throw new ValidationException($emptyCode, 'At least one ingredient line is required.', 'ingredients');
        }

        foreach ($lines as $i => $line) {
            for ($j = 0; $j < $i; ++$j) {
                if ($lines[$j]->ref->equals($line->ref)) {
                    throw new ValidationException($duplicateCode, \sprintf('Line %d repeats the ingredient of line %d (%s).', $i + 1, $j + 1, self::describe($line->ref)), 'ingredients');
                }
            }
        }
    }

    /**
     * How a line's target is named in error messages: kind and id, so that
     * an API client can find the line it sent.
     */
    public static function describe(IngredientRef $ref): string
    {
        return $ref->isProduct()
            ? 'product '.$ref->productId()
            : 'component '.$ref->componentId();
    }
}
