<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\Dish;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\DishId;

interface DishRepository
{
    /**
     * @throws NotFoundException when there is no dish with this id
     */
    public function get(DishId $id): Dish;

    /**
     * @return list<Dish>
     */
    public function all(): array;

    public function add(Dish $dish): void;
}
