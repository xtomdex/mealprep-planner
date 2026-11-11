<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Lets an aggregate collect the events its own methods produce.
 *
 * The aggregate only records; it never dispatches. Whoever saved it pulls the
 * events out afterwards and publishes them, so nothing is announced for a
 * change that did not reach the database, and the domain needs no dispatcher.
 *
 * A trait rather than a base class: entities keep their single inheritance
 * slot and the persistence mapping does not have to know about a parent.
 */
trait AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /**
     * Returns the events in the order they were recorded and forgets them,
     * so that a second save of the same object cannot publish them twice.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /**
     * Not public on purpose: only the aggregate's own behaviour decides that
     * something happened; outside code cannot plant events in it.
     */
    protected function recordEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
