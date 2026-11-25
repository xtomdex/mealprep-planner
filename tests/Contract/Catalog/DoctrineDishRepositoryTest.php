<?php

declare(strict_types=1);

namespace App\Tests\Contract\Catalog;

use App\Catalog\Domain\Repository\DishRepository;
use App\Catalog\Infrastructure\Persistence\DoctrineDishRepository;
use App\Tests\Support\Persistence\DoctrineContract;

final class DoctrineDishRepositoryTest extends DishRepositoryContract
{
    use DoctrineContract;

    protected function repository(): DishRepository
    {
        return new DoctrineDishRepository($this->entityManager());
    }

    public function testAllLoadsDishesAndLinesInOneQuery(): void
    {
        $this->added('Rice bowl', [$this->existingProduct(), $this->existingProduct()]);
        $this->added('Salad', [$this->existingProduct(), $this->existingProduct()]);
        $this->flushAndClear();

        $lines = 0;
        self::assertSame(1, $this->queriesOf(function () use (&$lines): void {
            foreach ($this->repository()->all() as $dish) {
                $lines += \count($dish->ingredients());
            }
        }));
        self::assertSame(4, $lines);
    }
}
