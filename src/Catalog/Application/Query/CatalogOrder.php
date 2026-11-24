<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Application\View\ComponentSummaryView;
use App\Catalog\Application\View\ProductView;

/**
 * The one order every catalog list is shown in: by name, ignoring case, so
 * "apple" does not land after "Zucchini"; equal names (possible for
 * components and dishes, which have no unique name) fall back to the id, so
 * the same catalog always lists the same way.
 *
 * The handler sorts rather than the repository: all() promises no order, and
 * an order that tests pin belongs where the tests can see it. The Doctrine
 * repository may push it into ORDER BY later without changing the result.
 *
 * @internal shared by the catalog list queries only
 */
final class CatalogOrder
{
    private function __construct()
    {
    }

    /**
     * mb_strtolower, not strcasecmp: the latter folds ASCII only, so "Éclair"
     * and "éclair" would be two different names. strcmp, not <=>: <=> compares
     * strings that look like numbers as numbers, so "10" would sort after "9"
     * while "10a" sorts before "9a".
     *
     * @template T of ProductView|ComponentSummaryView
     *
     * @param list<T> $views
     *
     * @return list<T>
     */
    public static function byName(array $views): array
    {
        usort($views, static function (ProductView|ComponentSummaryView $a, ProductView|ComponentSummaryView $b): int {
            $byName = strcmp(mb_strtolower($a->name), mb_strtolower($b->name));

            return 0 !== $byName ? $byName : strcmp($a->id, $b->id);
        });

        return $views;
    }
}
