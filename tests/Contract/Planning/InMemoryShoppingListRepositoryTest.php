<?php

declare(strict_types=1);

namespace App\Tests\Contract\Planning;

use App\Planning\Domain\Repository\ShoppingListRepository;
use App\Tests\Support\Planning\InMemoryShoppingListRepository;

final class InMemoryShoppingListRepositoryTest extends ShoppingListRepositoryContract
{
    private ShoppingListRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryShoppingListRepository();
    }

    protected function repository(): ShoppingListRepository
    {
        return $this->repository;
    }

    protected function secondListFailure(): string
    {
        return \LogicException::class;
    }
}
