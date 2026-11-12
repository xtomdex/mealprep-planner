<?php

declare(strict_types=1);

namespace App\Tests\Support\Planning;

use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Repository\MealPlanRepository;
use App\Shared\Domain\Exception\NotFoundException;

/**
 * A working MealPlanRepository backed by an array, used instead of mocks;
 * see InMemoryProductRepository for why.
 */
final class InMemoryMealPlanRepository implements MealPlanRepository
{
    /** @var array<string, MealPlan> keyed by id value */
    private array $plans = [];

    public function get(MealPlanId $id): MealPlan
    {
        return $this->plans[$id->value] ?? throw new NotFoundException('MealPlan', $id->value);
    }

    public function all(): array
    {
        return array_values($this->plans);
    }

    public function add(MealPlan $plan): void
    {
        $this->plans[$plan->id()->value] = $plan;
    }
}
