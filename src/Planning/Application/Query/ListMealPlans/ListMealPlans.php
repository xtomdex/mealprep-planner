<?php

declare(strict_types=1);

namespace App\Planning\Application\Query\ListMealPlans;

use App\Shared\Application\Bus\Query;

/**
 * Every plan, newest period first, without its entries.
 */
final readonly class ListMealPlans implements Query
{
}
