<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\ListDishes;

use App\Shared\Application\Bus\Query;

/**
 * Every dish, sorted by name, without its lines (GetDish has them).
 */
final readonly class ListDishes implements Query
{
}
