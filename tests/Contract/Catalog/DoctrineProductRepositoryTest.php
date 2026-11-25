<?php

declare(strict_types=1);

namespace App\Tests\Contract\Catalog;

use App\Catalog\Domain\Repository\ProductRepository;
use App\Catalog\Infrastructure\Persistence\DoctrineProductRepository;
use App\Tests\Support\Persistence\DoctrineContract;

final class DoctrineProductRepositoryTest extends ProductRepositoryContract
{
    use DoctrineContract;

    protected function repository(): ProductRepository
    {
        return new DoctrineProductRepository($this->entityManager());
    }

    public function testFindByIdsOfNoIdsSendsNoQuery(): void
    {
        self::assertSame(0, $this->queriesOf(fn () => $this->repository()->findByIds([])));
    }
}
