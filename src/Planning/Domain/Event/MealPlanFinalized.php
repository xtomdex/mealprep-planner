<?php

declare(strict_types=1);

namespace App\Planning\Domain\Event;

use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Domain\Event\DomainEvent;

/**
 * A plan was frozen. Carries the id, not the plan: a listener that needs
 * more loads the current state itself instead of trusting a stale copy.
 */
final readonly class MealPlanFinalized implements DomainEvent
{
    public function __construct(
        public MealPlanId $planId,
        public \DateTimeImmutable $finalizedAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->finalizedAt;
    }
}
