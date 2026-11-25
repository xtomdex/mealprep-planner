<?php

declare(strict_types=1);

namespace App\Planning\Infrastructure\Persistence\Type;

use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Infrastructure\Persistence\Type\UuidType;

/**
 * @extends UuidType<MealPlanId>
 */
final class MealPlanIdType extends UuidType
{
    public const string NAME = 'meal_plan_id';

    protected function idClass(): string
    {
        return MealPlanId::class;
    }
}
