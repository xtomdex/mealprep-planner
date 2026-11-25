<?php

declare(strict_types=1);

namespace App\Tests\Support\Shared\Event;

use App\Shared\Domain\Event\DomainEvent;

/**
 * Subscribes to every domain event on event.bus (Messenger matches handlers
 * by interface too) and keeps what it received, in order.
 */
final class RecordingEventSubscriber
{
    /** @var list<DomainEvent> */
    private array $received = [];

    public function __invoke(DomainEvent $event): void
    {
        $this->received[] = $event;
    }

    /**
     * @return list<DomainEvent>
     */
    public function received(): array
    {
        return $this->received;
    }
}
