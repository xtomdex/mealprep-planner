<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Application\Command;

use App\Planning\Application\Command\CreateMealPlan\CreateMealPlan;
use App\Planning\Application\Command\CreateMealPlan\CreateMealPlanHandler;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\MealPlanStatus;
use App\Shared\Domain\Exception\ValidationException;
use App\Tests\Support\Planning\InMemoryMealPlanRepository;
use App\Tests\Support\Shared\ImmediateTransactionalRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreateMealPlanHandlerTest extends TestCase
{
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';

    private InMemoryMealPlanRepository $plans;

    private ImmediateTransactionalRunner $transaction;

    private MockClock $clock;

    private CreateMealPlanHandler $handler;

    protected function setUp(): void
    {
        $this->plans = new InMemoryMealPlanRepository();
        $this->transaction = new ImmediateTransactionalRunner();
        // Madrid, not UTC: the handler must convert, and the tests below see
        // whether it does.
        $this->clock = new MockClock('2026-11-17 10:00:00', 'Europe/Madrid');
        $this->handler = new CreateMealPlanHandler($this->plans, $this->transaction, $this->clock);
    }

    public function testStoresAnEmptyDraftWithTheClocksTime(): void
    {
        ($this->handler)(new CreateMealPlan(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-23')));

        $plan = $this->plans->get(new MealPlanId(self::PLAN));
        self::assertSame(MealPlanStatus::Draft, $plan->status());
        self::assertSame('2026-11-23', $plan->periodStart()->format('Y-m-d'));
        self::assertSame([], $plan->entries());
        self::assertEquals($this->clock->now(), $plan->createdAt());
        self::assertSame(1, $this->transaction->runs());
    }

    public function testPln01AStartBeforeTheClocksUtcDateIsRejectedAndNothingIsStored(): void
    {
        try {
            ($this->handler)(new CreateMealPlan(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-16')));
            self::fail('Expected PLN-01.');
        } catch (ValidationException $e) {
            self::assertSame('PLN-01', $e->errorCode());
        }

        self::assertSame([], $this->plans->all());
    }

    /**
     * Pins the time-zone decision: at 23:30 UTC it is already the next day
     * in Spain, yet the UTC date still counts as today. The clock reports
     * Madrid time, so the test fails if the handler takes the date in the
     * clock's own zone instead of UTC.
     */
    public function testPln01TodayIsTheUtcDateSoAStartOnItPassesAt2330Utc(): void
    {
        $this->clock->modify('2026-11-18 00:30:00');
        self::assertSame('2026-11-17 23:30', $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i'));

        ($this->handler)(new CreateMealPlan(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-17')));

        self::assertSame('2026-11-17', $this->plans->get(new MealPlanId(self::PLAN))->periodStart()->format('Y-m-d'));
    }
}
