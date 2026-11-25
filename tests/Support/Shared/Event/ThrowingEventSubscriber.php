<?php

declare(strict_types=1);

namespace App\Tests\Support\Shared\Event;

use App\Shared\Domain\Event\DomainEvent;

/**
 * Fails on every domain event once armed. Disarmed by default, so it stays
 * out of the way of tests that dispatch events for other reasons.
 */
final class ThrowingEventSubscriber
{
    private bool $armed = false;

    public function arm(): void
    {
        $this->armed = true;
    }

    public function __invoke(DomainEvent $event): void
    {
        if ($this->armed) {
            throw new \RuntimeException(\sprintf('Subscriber failed on %s.', $event::class));
        }
    }
}
