<?php

declare(strict_types=1);

namespace App\Tests\Contract\Planning;

use App\Planning\Domain\Repository\MealPlanRepository;
use App\Planning\Infrastructure\Persistence\DoctrineMealPlanRepository;
use App\Tests\Support\Persistence\DoctrineContract;

final class DoctrineMealPlanRepositoryTest extends MealPlanRepositoryContract
{
    use DoctrineContract;

    protected function repository(): MealPlanRepository
    {
        return new DoctrineMealPlanRepository($this->entityManager());
    }
}
