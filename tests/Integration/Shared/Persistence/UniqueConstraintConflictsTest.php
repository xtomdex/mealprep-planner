<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Persistence;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\Product;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Shared\Infrastructure\Persistence\DoctrineTransactionalRunner;
use App\Tests\Support\Persistence\MappingTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\NullLogger;

/**
 * Through the runner, as a handler would write: a taken name comes out as
 * the domain's conflict with its invariant code.
 */
final class UniqueConstraintConflictsTest extends MappingTestCase
{
    public function testATakenProductNameIsAConflictWithPrd02(): void
    {
        $this->runner()->run(fn () => $this->em()->persist($this->product('Rice')));

        $this->assertConflict('PRD-02', fn () => $this->em()->persist($this->product('Rice')));
    }

    public function testATakenComponentNameIsAConflictWithCmp05(): void
    {
        $rice = $this->product('Rice');
        $this->runner()->run(fn () => $this->em()->persist($rice));
        $this->runner()->run(fn () => $this->em()->persist($this->component('Cooked rice', $rice->id())));

        $this->assertConflict('CMP-05', fn () => $this->em()->persist($this->component('Cooked rice', $rice->id())));
    }

    public function testATakenDishNameIsAConflictWithDsh04(): void
    {
        $rice = $this->product('Rice');
        $this->runner()->run(fn () => $this->em()->persist($rice));
        $this->runner()->run(fn () => $this->em()->persist($this->dish('Rice bowl', $rice->id())));

        $this->assertConflict('DSH-04', fn () => $this->em()->persist($this->dish('Rice bowl', $rice->id())));
    }

    /**
     * The plan merges a repeated (day, dish), so only a bug (here: raw SQL)
     * can hit this constraint; it must stay a server error.
     */
    public function testAnyOtherUniqueViolationIsRethrownUnchanged(): void
    {
        $rice = $this->product('Rice');
        $dish = $this->dish('Rice bowl', $rice->id());
        $plan = MealPlan::create(new MealPlanId(self::nextId()), new \DateTimeImmutable('2026-11-30'), new \DateTimeImmutable('2026-11-25'), new \DateTimeImmutable());
        $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-11-30'), $dish->id(), 1, new \DateTimeImmutable());
        $this->runner()->run(function () use ($rice, $dish, $plan): void {
            $this->em()->persist($rice);
            $this->em()->flush();
            $this->em()->persist($dish);
            $this->em()->flush();
            $this->em()->persist($plan);
        });

        $this->expectException(UniqueConstraintViolationException::class);
        $this->expectExceptionMessage('uq_planning_meal_plan_entries_meal_plan_id_day_dish_id');

        $this->runner()->run(fn () => $this->connection()->insert('planning_meal_plan_entries', [
            'id' => self::nextId(),
            'meal_plan_id' => $plan->id()->value,
            'day' => '2026-11-30',
            'dish_id' => $dish->id()->value,
            'portions' => 1,
        ]));
    }

    /**
     * @param callable(): mixed $write
     */
    private function assertConflict(string $code, callable $write): void
    {
        try {
            $this->runner()->run($write);
        } catch (ConflictException $e) {
            self::assertSame($code, $e->errorCode());

            return;
        }

        self::fail(\sprintf('Expected a conflict %s.', $code));
    }

    private function runner(): DoctrineTransactionalRunner
    {
        return new DoctrineTransactionalRunner(self::getContainer()->get(ManagerRegistry::class), self::getContainer()->get('event.bus'), new NullLogger());
    }

    private function product(string $name): Product
    {
        return Product::create(new ProductId(self::nextId()), $name, MeasurementCategory::Weight, new \DateTimeImmutable());
    }

    private function component(string $name, ProductId $product): Component
    {
        return Component::create(new ComponentId(self::nextId()), $name, 1, [new IngredientLine(IngredientRef::product($product), Quantity::of('100', Unit::Gram))], new \DateTimeImmutable());
    }

    private function dish(string $name, ProductId $product): Dish
    {
        return Dish::create(new DishId(self::nextId()), $name, [new IngredientLine(IngredientRef::product($product), Quantity::of('100', Unit::Gram))], new \DateTimeImmutable());
    }
}
