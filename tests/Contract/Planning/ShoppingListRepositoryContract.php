<?php

declare(strict_types=1);

namespace App\Tests\Contract\Planning;

use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ProductRequirement;
use App\Planning\Domain\Model\ShoppingItem;
use App\Planning\Domain\Model\ShoppingList;
use App\Planning\Domain\Model\ShoppingListId;
use App\Planning\Domain\Repository\ShoppingListRepository;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Persistence\RepositoryContract;

abstract class ShoppingListRepositoryContract extends RepositoryContract
{
    abstract protected function repository(): ShoppingListRepository;

    /**
     * Where SHL-01 is enforced differs: the fake refuses at add(), the
     * database at the flush, each with its own exception. The contract pins
     * that the second list never gets stored, not how it is refused.
     *
     * @return class-string<\Throwable>
     */
    abstract protected function secondListFailure(): string;

    public function testAnAddedListIsFoundByItsPlanWithItsItemsInOrder(): void
    {
        $plan = $this->existingPlan();
        $id = new ShoppingListId(self::nextId());
        $this->repository()->add(ShoppingList::generate($id, $plan, [$this->requirement('Tomato', '337.5'), $this->requirement('Rice', '450')], new \DateTimeImmutable()));
        $this->flushAndClear();

        $list = $this->repository()->findByPlan($plan);

        self::assertNotNull($list);
        self::assertTrue($list->id()->equals($id));
        self::assertSame([[1, 'Tomato', '338 g'], [2, 'Rice', '450 g']], self::items($list));
    }

    public function testFindByPlanIsNullForAPlanWithoutAList(): void
    {
        self::assertNull($this->repository()->findByPlan($this->existingPlan()));
        self::assertNull($this->repository()->findByPlan(new MealPlanId(self::nextId())));
    }

    public function testASecondListForTheSamePlanIsRefused(): void
    {
        $plan = $this->existingPlan();
        $this->repository()->add(ShoppingList::generate(new ShoppingListId(self::nextId()), $plan, [$this->requirement('Rice', '1')], new \DateTimeImmutable()));
        $this->flushAndClear();

        $this->expectException($this->secondListFailure());

        $this->repository()->add(ShoppingList::generate(new ShoppingListId(self::nextId()), $plan, [$this->requirement('Rice', '1')], new \DateTimeImmutable()));
        $this->flushAndClear();
    }

    protected function requirement(string $name, string $grams): ProductRequirement
    {
        return new ProductRequirement(new ProductId(self::nextId()), $name, Quantity::of($grams, Unit::Gram));
    }

    /**
     * @return list<array{int, string, string}>
     */
    protected static function items(ShoppingList $list): array
    {
        return array_map(static fn (ShoppingItem $item): array => [$item->position(), $item->productName(), (string) $item->quantity()], $list->items());
    }
}
