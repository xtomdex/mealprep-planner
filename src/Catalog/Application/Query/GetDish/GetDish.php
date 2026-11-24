<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetDish;

use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Identity\DishId;

/**
 * One dish with its lines and the names of the ingredients they use.
 */
final readonly class GetDish implements Query
{
    public function __construct(
        public DishId $dishId,
    ) {
    }
}
