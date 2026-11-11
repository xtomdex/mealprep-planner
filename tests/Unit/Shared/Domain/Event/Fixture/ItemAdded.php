<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event\Fixture;

use App\Shared\Domain\Event\DomainEvent;

final readonly class ItemAdded implements DomainEvent
{
    public function __construct(
        public string $item,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
