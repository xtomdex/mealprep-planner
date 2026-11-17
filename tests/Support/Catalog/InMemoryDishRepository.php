<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Repository\DishRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\DishId;

/**
 * A working DishRepository backed by an array; see InMemoryProductRepository
 * for why fakes and not mocks.
 */
final class InMemoryDishRepository implements DishRepository
{
    /** @var array<string, Dish> keyed by id value */
    private array $dishes = [];

    public function get(DishId $id): Dish
    {
        return $this->dishes[$id->value] ?? throw new NotFoundException('Dish', $id->value);
    }

    public function all(): array
    {
        return array_values($this->dishes);
    }

    public function add(Dish $dish): void
    {
        $this->dishes[$dish->id()->value] = $dish;
    }
}
