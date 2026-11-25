<?php

declare(strict_types=1);

namespace App\Tests\Contract\Catalog;

use App\Catalog\Domain\Repository\ComponentRepository;
use App\Tests\Support\Catalog\InMemoryComponentRepository;

final class InMemoryComponentRepositoryTest extends ComponentRepositoryContract
{
    private ComponentRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryComponentRepository();
    }

    protected function repository(): ComponentRepository
    {
        return $this->repository;
    }
}
