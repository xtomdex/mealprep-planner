<?php

declare(strict_types=1);

namespace App\Planning\Application\Command\RemoveMealPlanEntry;

use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Application\Bus\Command;

/**
 * Removes one entry from a plan. Idempotent for a draft: an entry that is
 * not there (any more) is not an error, so a repeated DELETE succeeds.
 */
final readonly class RemoveMealPlanEntry implements Command
{
    public function __construct(
        public MealPlanId $planId,
        public MealPlanEntryId $entryId,
    ) {
    }
}
