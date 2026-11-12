<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Domain\Model;

use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ProductRequirement;
use App\Planning\Domain\Model\ShoppingItem;
use App\Planning\Domain\Model\ShoppingList;
use App\Planning\Domain\Model\ShoppingListId;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use PHPUnit\Framework\TestCase;

final class ShoppingListRefreshTest extends TestCase
{
    private const string LIST = '0193a5e0-7b1c-7a3e-9f00-000000000005';
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string OTHER_PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000002';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string OIL = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';

    public function testReplaceItemsKeepsIdentityAndTakesANewSnapshot(): void
    {
        $list = $this->list(new \DateTimeImmutable('2026-11-12 10:00'));
        $now = new \DateTimeImmutable('2026-11-13 10:00');

        $list->replaceItems([new ProductRequirement(new ProductId(self::OIL), 'Olive oil', Quantity::of('0.3335', Unit::Litre))], $now);

        self::assertTrue((new ShoppingListId(self::LIST))->equals($list->id()));
        self::assertTrue((new MealPlanId(self::PLAN))->equals($list->mealPlanId()));
        self::assertSame($now, $list->generatedAt());
        self::assertSame(['1 Olive oil 334 ml'], array_map(static fn (ShoppingItem $i): string => $i->position().' '.$i->productName().' '.$i->quantity(), $list->items()));
    }

    public function testShl02ReplaceItemsRejectsAnEmptyListAndKeepsTheOldItems(): void
    {
        $generatedAt = new \DateTimeImmutable('2026-11-12 10:00');
        $list = $this->list($generatedAt);

        $this->assertRejected('SHL-02', static fn () => $list->replaceItems([], new \DateTimeImmutable('2026-11-13')));
        self::assertCount(1, $list->items());
        self::assertSame($generatedAt, $list->generatedAt());
    }

    public function testShl03ReplaceItemsRejectsARepeatedProductAndKeepsTheOldItems(): void
    {
        $list = $this->list(new \DateTimeImmutable('2026-11-12 10:00'));
        $oil = new ProductRequirement(new ProductId(self::OIL), 'Olive oil', Quantity::of('1', Unit::Litre));

        $this->assertRejected('SHL-03', static fn () => $list->replaceItems([$oil, $oil], new \DateTimeImmutable('2026-11-13')));
        self::assertSame('Rice', $list->items()[0]->productName());
    }

    public function testAListIsFreshUntilThePlanEntriesChangeAfterItWasGenerated(): void
    {
        $plan = $this->plan();
        $this->addSoup($plan, '2026-11-12 10:00');
        $list = $this->list(new \DateTimeImmutable('2026-11-12 11:00'));

        self::assertFalse($list->isStaleFor($plan));

        $this->addSoup($plan, '2026-11-12 12:00');
        self::assertTrue($list->isStaleFor($plan));

        $list->replaceItems([new ProductRequirement(new ProductId(self::RICE), 'Rice', Quantity::of('1', Unit::Kilogram))], new \DateTimeImmutable('2026-11-12 13:00'));
        self::assertFalse($list->isStaleFor($plan));
    }

    public function testFinalizingThePlanDoesNotMakeTheListStale(): void
    {
        $plan = $this->plan();
        $this->addSoup($plan, '2026-11-12 10:00');
        $list = $this->list(new \DateTimeImmutable('2026-11-12 11:00'));

        $plan->finalize(new \DateTimeImmutable('2026-11-12 12:00'));

        self::assertFalse($list->isStaleFor($plan));
    }

    public function testIsStaleForAnotherPlanIsAProgrammingError(): void
    {
        $other = MealPlan::create(new MealPlanId(self::OTHER_PLAN), new \DateTimeImmutable('2026-11-16'), new \DateTimeImmutable('2026-11-12'), new \DateTimeImmutable());

        $this->expectException(\LogicException::class);
        $this->list(new \DateTimeImmutable())->isStaleFor($other);
    }

    private function plan(): MealPlan
    {
        return MealPlan::create(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-16'), new \DateTimeImmutable('2026-11-12'), new \DateTimeImmutable('2026-11-12 09:00'));
    }

    private function addSoup(MealPlan $plan, string $now): void
    {
        $plan->addEntry(new MealPlanEntryId(self::E1), new \DateTimeImmutable('2026-11-16'), new DishId(self::SOUP), 1, new \DateTimeImmutable($now));
    }

    private function list(\DateTimeImmutable $generatedAt): ShoppingList
    {
        return ShoppingList::generate(new ShoppingListId(self::LIST), new MealPlanId(self::PLAN), [
            new ProductRequirement(new ProductId(self::RICE), 'Rice', Quantity::of('500', Unit::Gram)),
        ], $generatedAt);
    }

    private function assertRejected(string $code, callable $action): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            self::assertSame($code, $e->errorCode());

            return;
        }

        self::fail(\sprintf('Expected a ValidationException %s.', $code));
    }
}
