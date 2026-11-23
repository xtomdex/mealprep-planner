<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Application\Command;

use App\Planning\Application\Command\RemoveMealPlanEntry\RemoveMealPlanEntry;
use App\Planning\Application\Command\RemoveMealPlanEntry\RemoveMealPlanEntryHandler;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntry;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Identity\DishId;
use App\Tests\Support\Planning\InMemoryMealPlanRepository;
use App\Tests\Support\Shared\ImmediateTransactionalRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class RemoveMealPlanEntryHandlerTest extends TestCase
{
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string SALAD = '0193a5e0-7b1c-7a3e-9f00-0000000000d2';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';
    private const string E2 = '0193a5e0-7b1c-7a3e-9f00-0000000000e2';
    private const string UNKNOWN_ENTRY = '0193a5e0-7b1c-7a3e-9f00-0000000000e9';

    private MealPlan $plan;

    private ImmediateTransactionalRunner $transaction;

    private MockClock $clock;

    private RemoveMealPlanEntryHandler $handler;

    protected function setUp(): void
    {
        $created = new \DateTimeImmutable('2026-11-12 10:00');
        $this->plan = MealPlan::create(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-16'), $created, $created);
        $this->plan->addEntry(new MealPlanEntryId(self::E1), new \DateTimeImmutable('2026-11-17'), new DishId(self::SOUP), 2, $created);
        $this->plan->addEntry(new MealPlanEntryId(self::E2), new \DateTimeImmutable('2026-11-18'), new DishId(self::SALAD), 1, $created);

        $plans = new InMemoryMealPlanRepository();
        $plans->add($this->plan);

        $this->transaction = new ImmediateTransactionalRunner();
        $this->clock = new MockClock('2026-11-13 09:00:00');
        $this->handler = new RemoveMealPlanEntryHandler($plans, $this->transaction, $this->clock);
    }

    public function testTheEntryIsRemovedAndTheOthersStay(): void
    {
        ($this->handler)($this->remove(self::E1));

        self::assertSame([self::E2], $this->entryIds());
        self::assertEquals($this->clock->now(), $this->plan->updatedAt());
        self::assertSame(1, $this->transaction->runs());
    }

    public function testRemovingAnUnknownEntryOfADraftIsANoOp(): void
    {
        $updatedAt = $this->plan->updatedAt();

        ($this->handler)($this->remove(self::UNKNOWN_ENTRY));

        self::assertSame([self::E1, self::E2], $this->entryIds());
        self::assertSame($updatedAt, $this->plan->updatedAt(), 'a no-op must not make the shopping list look stale');
    }

    public function testPln04AFinalizedPlanRefusesTheRemoval(): void
    {
        $this->plan->finalize(new \DateTimeImmutable('2026-11-13 08:00'));

        try {
            ($this->handler)($this->remove(self::E1));
            self::fail('Expected PLN-04.');
        } catch (ConflictException $e) {
            self::assertSame('PLN-04', $e->errorCode());
        }

        self::assertSame([self::E1, self::E2], $this->entryIds());
    }

    private function remove(string $entryId): RemoveMealPlanEntry
    {
        return new RemoveMealPlanEntry(new MealPlanId(self::PLAN), new MealPlanEntryId($entryId));
    }

    /**
     * @return list<string>
     */
    private function entryIds(): array
    {
        return array_map(static fn (MealPlanEntry $e): string => $e->id()->value, $this->plan->entries());
    }
}
