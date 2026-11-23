<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Application\Command;

use App\Planning\Application\Command\FinalizeMealPlan\FinalizeMealPlan;
use App\Planning\Application\Command\FinalizeMealPlan\FinalizeMealPlanHandler;
use App\Planning\Domain\Event\MealPlanFinalized;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\MealPlanStatus;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Identity\DishId;
use App\Tests\Support\Planning\InMemoryMealPlanRepository;
use App\Tests\Support\Shared\ImmediateTransactionalRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class FinalizeMealPlanHandlerTest extends TestCase
{
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';

    private MealPlan $plan;

    private ImmediateTransactionalRunner $transaction;

    private MockClock $clock;

    private FinalizeMealPlanHandler $handler;

    protected function setUp(): void
    {
        $created = new \DateTimeImmutable('2026-11-12 10:00');
        $this->plan = MealPlan::create(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-16'), $created, $created);

        $plans = new InMemoryMealPlanRepository();
        $plans->add($this->plan);

        $this->transaction = new ImmediateTransactionalRunner();
        $this->clock = new MockClock('2026-11-13 09:00:00');
        $this->handler = new FinalizeMealPlanHandler($plans, $this->transaction, $this->clock);
    }

    public function testThePlanIsFinalizedAtTheClocksTimeAndRecordsTheEvent(): void
    {
        $this->addSoup();

        ($this->handler)(new FinalizeMealPlan(new MealPlanId(self::PLAN)));

        self::assertSame(MealPlanStatus::Finalized, $this->plan->status());
        self::assertEquals($this->clock->now(), $this->plan->finalizedAt());
        self::assertSame(1, $this->transaction->runs());

        // Still recorded: nothing publishes domain events yet.
        $events = $this->plan->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MealPlanFinalized::class, $events[0]);
        self::assertTrue($this->plan->id()->equals($events[0]->planId));
    }

    public function testPln05AnEmptyPlanCannotBeFinalized(): void
    {
        $this->assertConflict('PLN-05');

        self::assertSame(MealPlanStatus::Draft, $this->plan->status());
        self::assertSame([], $this->plan->releaseEvents());
    }

    public function testPln04FinalizingTwiceIsRefused(): void
    {
        $this->addSoup();
        ($this->handler)(new FinalizeMealPlan(new MealPlanId(self::PLAN)));
        $this->plan->releaseEvents();

        $this->assertConflict('PLN-04');

        self::assertSame([], $this->plan->releaseEvents(), 'no second event');
    }

    private function addSoup(): void
    {
        $this->plan->addEntry(new MealPlanEntryId(self::E1), new \DateTimeImmutable('2026-11-17'), new DishId(self::SOUP), 1, new \DateTimeImmutable('2026-11-12 11:00'));
    }

    private function assertConflict(string $code): void
    {
        try {
            ($this->handler)(new FinalizeMealPlan(new MealPlanId(self::PLAN)));
            self::fail(\sprintf('Expected %s.', $code));
        } catch (ConflictException $e) {
            self::assertSame($code, $e->errorCode());
        }
    }
}
