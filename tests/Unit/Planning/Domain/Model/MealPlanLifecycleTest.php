<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Domain\Model;

use App\Planning\Domain\Event\MealPlanFinalized;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\MealPlanStatus;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Identity\DishId;
use PHPUnit\Framework\TestCase;

final class MealPlanLifecycleTest extends TestCase
{
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';
    private const string E2 = '0193a5e0-7b1c-7a3e-9f00-0000000000e2';

    public function testFinalizeFreezesThePlanAndRecordsTheEventOnce(): void
    {
        $plan = $this->planWithOneEntry();
        $updatedAt = $plan->updatedAt();
        $now = new \DateTimeImmutable('2026-11-14 08:00');

        $plan->finalize($now);

        self::assertSame(MealPlanStatus::Finalized, $plan->status());
        self::assertSame($now, $plan->finalizedAt());
        self::assertSame($updatedAt, $plan->updatedAt(), 'finalize must not touch updatedAt, or every list would turn stale');

        $events = $plan->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MealPlanFinalized::class, $events[0]);
        self::assertTrue($plan->id()->equals($events[0]->planId));
        self::assertSame($now, $events[0]->occurredAt());
        self::assertSame([], $plan->releaseEvents());
    }

    public function testADraftHasNoFinalizedAtAndNoEvents(): void
    {
        $plan = $this->planWithOneEntry();

        self::assertNull($plan->finalizedAt());
        self::assertSame([], $plan->releaseEvents());
    }

    public function testPln05RejectsFinalizingAPlanWithoutEntries(): void
    {
        $plan = $this->emptyPlan();

        $this->assertConflict('PLN-05', static fn () => $plan->finalize(new \DateTimeImmutable()));
        self::assertSame(MealPlanStatus::Draft, $plan->status());
        self::assertSame([], $plan->releaseEvents());
    }

    public function testPln04RejectsFinalizingTwice(): void
    {
        $plan = $this->finalizedPlan();

        $this->assertConflict('PLN-04', static fn () => $plan->finalize(new \DateTimeImmutable()));
        self::assertSame([], $plan->releaseEvents(), 'no second event');
    }

    public function testPln04RejectsAddingAnEntryToAFinalizedPlan(): void
    {
        $plan = $this->finalizedPlan();

        $this->assertConflict('PLN-04', static fn () => $plan->addEntry(new MealPlanEntryId(self::E2), new \DateTimeImmutable('2026-11-17'), new DishId(self::SOUP), 1, new \DateTimeImmutable()));
        self::assertCount(1, $plan->entries());
    }

    public function testPln04RejectsRemovingAnEntryFromAFinalizedPlan(): void
    {
        $plan = $this->finalizedPlan();

        $this->assertConflict('PLN-04', static fn () => $plan->removeEntry(new MealPlanEntryId(self::E1), new \DateTimeImmutable()));
        self::assertCount(1, $plan->entries());
    }

    public function testPln04RejectsRemovingAnUnknownEntryFromAFinalizedPlanToo(): void
    {
        $plan = $this->finalizedPlan();

        $this->assertConflict('PLN-04', static fn () => $plan->removeEntry(new MealPlanEntryId(self::E2), new \DateTimeImmutable()));
    }

    private function emptyPlan(): MealPlan
    {
        return MealPlan::create(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-16'), new \DateTimeImmutable('2026-11-12'), new \DateTimeImmutable('2026-11-12 10:00'));
    }

    private function planWithOneEntry(): MealPlan
    {
        $plan = $this->emptyPlan();
        $plan->addEntry(new MealPlanEntryId(self::E1), new \DateTimeImmutable('2026-11-16'), new DishId(self::SOUP), 2, new \DateTimeImmutable('2026-11-12 11:00'));

        return $plan;
    }

    private function finalizedPlan(): MealPlan
    {
        $plan = $this->planWithOneEntry();
        $plan->finalize(new \DateTimeImmutable('2026-11-14 08:00'));
        $plan->releaseEvents();

        return $plan;
    }

    private function assertConflict(string $code, callable $action): void
    {
        try {
            $action();
        } catch (ConflictException $e) {
            self::assertSame($code, $e->errorCode());

            return;
        }

        self::fail(\sprintf('Expected a ConflictException %s.', $code));
    }
}
