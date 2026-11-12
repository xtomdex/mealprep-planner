<?php

declare(strict_types=1);

namespace App\Planning\Domain\Repository;

use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Domain\Exception\NotFoundException;

interface MealPlanRepository
{
    /**
     * @throws NotFoundException when there is no plan with this id
     */
    public function get(MealPlanId $id): MealPlan;

    /**
     * @return list<MealPlan>
     */
    public function all(): array;

    public function add(MealPlan $plan): void;
}
