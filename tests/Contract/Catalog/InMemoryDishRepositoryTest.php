<?php

declare(strict_types=1);

namespace App\Tests\Contract\Catalog;

use App\Catalog\Domain\Repository\DishRepository;
use App\Tests\Support\Catalog\InMemoryDishRepository;

final class InMemoryDishRepositoryTest extends DishRepositoryContract
{
    private DishRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryDishRepository();
    }

    protected function repository(): DishRepository
    {
        return $this->repository;
    }
}
