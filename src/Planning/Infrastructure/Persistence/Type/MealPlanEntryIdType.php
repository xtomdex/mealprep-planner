<?php

declare(strict_types=1);

namespace App\Planning\Infrastructure\Persistence\Type;

use App\Planning\Domain\Model\MealPlanEntryId;
use App\Shared\Infrastructure\Persistence\Type\UuidType;

/**
 * @extends UuidType<MealPlanEntryId>
 */
final class MealPlanEntryIdType extends UuidType
{
    public const string NAME = 'meal_plan_entry_id';

    protected function idClass(): string
    {
        return MealPlanEntryId::class;
    }
}
