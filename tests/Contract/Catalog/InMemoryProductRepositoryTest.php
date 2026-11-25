<?php

declare(strict_types=1);

namespace App\Tests\Contract\Catalog;

use App\Catalog\Domain\Repository\ProductRepository;
use App\Tests\Support\Catalog\InMemoryProductRepository;

final class InMemoryProductRepositoryTest extends ProductRepositoryContract
{
    private ProductRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryProductRepository();
    }

    protected function repository(): ProductRepository
    {
        return $this->repository;
    }
}
