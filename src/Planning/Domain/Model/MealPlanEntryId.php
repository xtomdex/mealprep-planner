<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

use App\Shared\Domain\Identity\Uuid;

/**
 * Identifies one entry of a meal plan. Unlike catalog recipe lines, entries
 * get their own id: a client removes "this entry", and a position would
 * shift under it as soon as another entry is added or removed.
 */
final readonly class MealPlanEntryId extends Uuid
{
}
