<?php

declare(strict_types=1);

namespace App\Tests\Contract\Planning;

use App\Planning\Domain\Repository\ShoppingListRepository;
use App\Planning\Infrastructure\Persistence\DoctrineShoppingListRepository;
use App\Tests\Support\Persistence\DoctrineContract;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineShoppingListRepositoryTest extends ShoppingListRepositoryContract
{
    use DoctrineContract;

    protected function repository(): ShoppingListRepository
    {
        return new DoctrineShoppingListRepository($this->entityManager());
    }

    protected function secondListFailure(): string
    {
        return UniqueConstraintViolationException::class;
    }
}
