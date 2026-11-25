<?php

declare(strict_types=1);

namespace App\Tests\Contract\Planning;

use App\Planning\Domain\Repository\MealPlanRepository;
use App\Tests\Support\Planning\InMemoryMealPlanRepository;

final class InMemoryMealPlanRepositoryTest extends MealPlanRepositoryContract
{
    private MealPlanRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryMealPlanRepository();
    }

    protected function repository(): MealPlanRepository
    {
        return $this->repository;
    }
}
