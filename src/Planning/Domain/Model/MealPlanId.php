<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

use App\Shared\Domain\Identity\Uuid;

/**
 * Identifies a meal plan. Only planning refers to plans, so the id lives in
 * the module instead of the shared kernel.
 */
final readonly class MealPlanId extends Uuid
{
}
