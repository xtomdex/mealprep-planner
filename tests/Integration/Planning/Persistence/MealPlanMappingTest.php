<?php

declare(strict_types=1);

namespace App\Tests\Integration\Planning\Persistence;

use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\Product;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\MealPlanStatus;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Persistence\MappingTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class MealPlanMappingTest extends MappingTestCase
{
    private const string TODAY = '2026-11-25';

    public function testAFinalizedPlanRoundTripsWithItsEntriesByDay(): void
    {
        [$soup, $salad] = [$this->dish('Soup'), $this->dish('Salad')];
        $id = new MealPlanId(self::nextId());
        $plan = $this->plan($id);
        $now = new \DateTimeImmutable('2026-11-25 18:00:00.250000');
        $later = $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-12-02'), $soup, 2, $now);
        $first = $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-11-30'), $salad, 1, $now);
        $second = $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-11-30'), $soup, 3, $now);
        $finalizedAt = new \DateTimeImmutable('2026-11-25 18:05:00.999999');
        $plan->finalize($finalizedAt);
        $this->em()->persist($plan);
        $this->flushAndClear();

        $loaded = $this->em()->find(MealPlan::class, $id);

        self::assertNotNull($loaded);
        self::assertSame(MealPlanStatus::Finalized, $loaded->status());
        self::assertSame($finalizedAt->format('U.u'), $loaded->finalizedAt()?->format('U.u'));
        self::assertSame($now->format('U.u'), $loaded->updatedAt()->format('U.u'));
        self::assertSame('2026-11-30', $loaded->periodStart()->format('Y-m-d'));
        self::assertSame('2026-12-06', $loaded->periodEnd()->format('Y-m-d'));
        self::assertSame([], $loaded->releaseEvents(), 'Events are not stored with the plan.');

        $entries = array_map(
            static fn ($entry): array => [(string) $entry->id(), $entry->day()->format('Y-m-d'), (string) $entry->dishId(), $entry->portions()],
            $loaded->entries(),
        );
        self::assertSame([
            [(string) $first, '2026-11-30', (string) $salad, 1],
            [(string) $second, '2026-11-30', (string) $soup, 3],
            [(string) $later, '2026-12-02', (string) $soup, 2],
        ], $entries);
    }

    public function testRemovingAnEntryDeletesItsRow(): void
    {
        $soup = $this->dish('Soup');
        $id = new MealPlanId(self::nextId());
        $plan = $this->plan($id);
        $kept = $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-11-30'), $soup, 1, new \DateTimeImmutable());
        $removed = $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-12-01'), $soup, 1, new \DateTimeImmutable());
        $this->em()->persist($plan);
        $this->flushAndClear();

        $this->em()->find(MealPlan::class, $id)?->removeEntry($removed, new \DateTimeImmutable());
        $this->flushAndClear();

        self::assertSame([$kept->value], $this->connection()->fetchFirstColumn('SELECT id FROM planning_meal_plan_entries WHERE meal_plan_id = ?', [$id->value]));
    }

    /**
     * The plan merges a repeated (day, dish) into one entry, so only SQL can
     * write the duplicate.
     */
    public function testTheUniqueConstraintRefusesASecondEntryForTheSameDayAndDish(): void
    {
        $id = $this->planWithOneEntry($soup = $this->dish('Soup'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->expectExceptionMessage('uq_planning_meal_plan_entries_meal_plan_id_day_dish_id');

        $this->insertEntry($id, $soup, 1);
    }

    public function testTheCheckRefusesAnEntryWithoutPortions(): void
    {
        $id = $this->planWithOneEntry($this->dish('Soup'));

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_planning_meal_plan_entries_portions_positive');

        $this->insertEntry($id, $this->dish('Salad'), 0);
    }

    /**
     * PLN-07 is checked by the handler; the foreign key catches code that
     * skips it.
     */
    public function testAnEntryForAMissingDishIsRefusedByItsForeignKey(): void
    {
        $plan = $this->plan(new MealPlanId(self::nextId()));
        $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-11-30'), new DishId(self::nextId()), 1, new \DateTimeImmutable());
        $this->em()->persist($plan);

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->expectExceptionMessage('fk_planning_meal_plan_entries_dish_id');

        $this->em()->flush();
    }

    private function plan(MealPlanId $id): MealPlan
    {
        return MealPlan::create($id, new \DateTimeImmutable('2026-11-30'), new \DateTimeImmutable(self::TODAY), new \DateTimeImmutable('2026-11-25 09:00:00'));
    }

    private function planWithOneEntry(DishId $dish): MealPlanId
    {
        $id = new MealPlanId(self::nextId());
        $plan = $this->plan($id);
        $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-11-30'), $dish, 1, new \DateTimeImmutable());
        $this->em()->persist($plan);
        $this->em()->flush();

        return $id;
    }

    private function insertEntry(MealPlanId $plan, DishId $dish, int $portions): void
    {
        $this->connection()->insert('planning_meal_plan_entries', [
            'id' => self::nextId(),
            'meal_plan_id' => $plan->value,
            'day' => '2026-11-30',
            'dish_id' => $dish->value,
            'portions' => $portions,
        ]);
    }

    private function dish(string $name): DishId
    {
        $product = new ProductId(self::nextId());
        $this->em()->persist(Product::create($product, $name.' base', MeasurementCategory::Weight, new \DateTimeImmutable()));
        $this->em()->flush();

        $id = new DishId(self::nextId());
        $this->em()->persist(Dish::create($id, $name, [new IngredientLine(IngredientRef::product($product), Quantity::of('100', Unit::Gram))], new \DateTimeImmutable()));
        $this->em()->flush();

        return $id;
    }
}
