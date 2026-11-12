<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Domain\Model;

use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntry;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\DishId;
use PHPUnit\Framework\TestCase;

final class MealPlanEntriesTest extends TestCase
{
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string SALAD = '0193a5e0-7b1c-7a3e-9f00-0000000000d2';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';
    private const string E2 = '0193a5e0-7b1c-7a3e-9f00-0000000000e2';
    private const string E3 = '0193a5e0-7b1c-7a3e-9f00-0000000000e3';

    private MealPlan $plan;

    protected function setUp(): void
    {
        // Monday 16 .. Sunday 22 November 2026.
        $this->plan = MealPlan::create(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-16'), new \DateTimeImmutable('2026-11-12'), new \DateTimeImmutable('2026-11-12 10:00'));
    }

    public function testAddEntryStoresTheDayAtMidnightAndMovesUpdatedAt(): void
    {
        $now = new \DateTimeImmutable('2026-11-12 11:00');

        $id = $this->plan->addEntry(new MealPlanEntryId(self::E1), new \DateTimeImmutable('2026-11-17 19:45'), new DishId(self::SOUP), 2, $now);

        self::assertTrue((new MealPlanEntryId(self::E1))->equals($id));
        [$entry] = $this->plan->entries();
        self::assertSame('2026-11-17 00:00:00', $entry->day()->format('Y-m-d H:i:s'));
        self::assertTrue((new DishId(self::SOUP))->equals($entry->dishId()));
        self::assertSame(2, $entry->portions());
        self::assertSame($this->plan, $entry->mealPlan());
        self::assertSame($now, $this->plan->updatedAt());
    }

    public function testPln02AcceptsTheFirstAndTheLastDayOfThePeriod(): void
    {
        $this->add(self::E1, '2026-11-16', self::SOUP);
        $this->add(self::E2, '2026-11-22 23:59', self::SOUP);

        self::assertCount(2, $this->plan->entries());
    }

    public function testPln02RejectsADayBeforeThePeriod(): void
    {
        $this->assertRejected('PLN-02', 'day', fn () => $this->add(self::E1, '2026-11-15 23:59', self::SOUP));
    }

    public function testPln02RejectsADayAfterThePeriod(): void
    {
        $this->assertRejected('PLN-02', 'day', fn () => $this->add(self::E1, '2026-11-23', self::SOUP));
    }

    public function testPln03RejectsZeroPortions(): void
    {
        $this->assertRejected('PLN-03', 'portions', fn () => $this->add(self::E1, '2026-11-16', self::SOUP, 0));
    }

    public function testPln06AddingTheSameDayAndDishAgainMergesPortionsIntoTheExistingEntry(): void
    {
        $this->add(self::E1, '2026-11-16 08:00', self::SOUP, 2);

        $id = $this->add(self::E2, '2026-11-16 20:00', self::SOUP, 3);

        self::assertTrue((new MealPlanEntryId(self::E1))->equals($id), 'the id of the existing entry is returned, not the new one');
        $entries = $this->plan->entries();
        self::assertCount(1, $entries);
        self::assertSame(5, $entries[0]->portions());
    }

    public function testPln06TheSameDishOnAnotherDayIsAnotherEntry(): void
    {
        $this->add(self::E1, '2026-11-16', self::SOUP);
        $this->add(self::E2, '2026-11-17', self::SOUP);

        self::assertCount(2, $this->plan->entries());
    }

    public function testEntriesAreOrderedByDayThenByInsertion(): void
    {
        $this->add(self::E1, '2026-11-18', self::SOUP);
        $this->add(self::E2, '2026-11-16', self::SALAD);
        $this->add(self::E3, '2026-11-16', self::SOUP);

        self::assertSame([self::E2, self::E3, self::E1], array_map(static fn (MealPlanEntry $e): string => $e->id()->value, $this->plan->entries()));
    }

    public function testRemoveEntryRemovesItAndMovesUpdatedAt(): void
    {
        $this->add(self::E1, '2026-11-16', self::SOUP);
        $this->add(self::E2, '2026-11-17', self::SOUP);
        $now = new \DateTimeImmutable('2026-11-13 09:00');

        $this->plan->removeEntry(new MealPlanEntryId(self::E1), $now);

        self::assertSame([self::E2], array_map(static fn (MealPlanEntry $e): string => $e->id()->value, $this->plan->entries()));
        self::assertSame($now, $this->plan->updatedAt());
    }

    public function testRemoveEntryWithAnUnknownIdChangesNothing(): void
    {
        $this->add(self::E1, '2026-11-16', self::SOUP);
        $before = $this->plan->updatedAt();

        $this->plan->removeEntry(new MealPlanEntryId(self::E2), new \DateTimeImmutable('2026-11-13 09:00'));

        self::assertCount(1, $this->plan->entries());
        self::assertSame($before, $this->plan->updatedAt());
    }

    private function add(string $entryId, string $day, string $dishId, int $portions = 1): MealPlanEntryId
    {
        return $this->plan->addEntry(new MealPlanEntryId($entryId), new \DateTimeImmutable($day), new DishId($dishId), $portions, new \DateTimeImmutable('2026-11-12 11:00'));
    }

    private function assertRejected(string $code, string $field, callable $action): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            self::assertSame($code, $e->errorCode());
            self::assertSame($field, $e->field());
            self::assertSame([], $this->plan->entries());

            return;
        }

        self::fail(\sprintf('Expected a ValidationException %s.', $code));
    }
}
