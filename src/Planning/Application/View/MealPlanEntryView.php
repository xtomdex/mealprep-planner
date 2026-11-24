<?php

declare(strict_types=1);

namespace App\Planning\Application\View;

final readonly class MealPlanEntryView
{
    /**
     * @param string $day Y-m-d
     */
    public function __construct(
        public string $id,
        public string $day,
        public string $dishId,
        public int $portions,
    ) {
    }
}
