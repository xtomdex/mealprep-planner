<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event;

use App\Tests\Unit\Shared\Domain\Event\Fixture\Basket;
use App\Tests\Unit\Shared\Domain\Event\Fixture\ItemAdded;
use PHPUnit\Framework\TestCase;

final class AggregateRootTest extends TestCase
{
    public function testNewAggregateHasNoEvents(): void
    {
        self::assertSame([], (new Basket())->releaseEvents());
    }

    public function testReleasesRecordedEventsInOrder(): void
    {
        $basket = new Basket();
        $basket->add('flour', new \DateTimeImmutable('2026-01-05 10:00:00'));
        $basket->add('butter', new \DateTimeImmutable('2026-01-05 10:00:01'));

        $events = $basket->releaseEvents();

        self::assertCount(2, $events);
        self::assertContainsOnlyInstancesOf(ItemAdded::class, $events);
        self::assertSame(['flour', 'butter'], array_map(static fn (ItemAdded $event): string => $event->item, $events));
    }

    public function testEventKeepsTheMomentItHappened(): void
    {
        $now = new \DateTimeImmutable('2026-01-05 10:00:00');
        $basket = new Basket();
        $basket->add('flour', $now);

        self::assertSame($now, $basket->releaseEvents()[0]->occurredAt());
    }

    /**
     * Guards against double publishing when the same object is saved twice.
     */
    public function testReleasingClearsTheEvents(): void
    {
        $basket = new Basket();
        $basket->add('flour', new \DateTimeImmutable('2026-01-05 10:00:00'));
        $basket->releaseEvents();

        self::assertSame([], $basket->releaseEvents());
    }

    public function testRecordsAgainAfterRelease(): void
    {
        $basket = new Basket();
        $basket->add('flour', new \DateTimeImmutable('2026-01-05 10:00:00'));
        $basket->releaseEvents();
        $basket->add('butter', new \DateTimeImmutable('2026-01-05 10:00:01'));

        self::assertCount(1, $basket->releaseEvents());
    }

    public function testAggregatesDoNotShareEvents(): void
    {
        $first = new Basket();
        $second = new Basket();
        $first->add('flour', new \DateTimeImmutable('2026-01-05 10:00:00'));

        self::assertSame([], $second->releaseEvents());
        self::assertCount(1, $first->releaseEvents());
    }
}
