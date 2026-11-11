<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * A fact that already happened inside an aggregate, named in the past tense.
 *
 * The contract is deliberately tiny: what an event carries is its own
 * business, but every event must say when it happened, because the moment
 * of recording and the moment of handling can be far apart.
 */
interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;
}
