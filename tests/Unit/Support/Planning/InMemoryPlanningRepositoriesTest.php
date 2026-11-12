<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\Planning;

use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ProductRequirement;
use App\Planning\Domain\Model\ShoppingList;
use App\Planning\Domain\Model\ShoppingListId;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Planning\InMemoryMealPlanRepository;
use App\Tests\Support\Planning\InMemoryShoppingListRepository;
use PHPUnit\Framework\TestCase;

/**
 * Pins the fakes' contract, as for the catalog fakes: a fake that lies makes
 * every test built on it lie.
 */
final class InMemoryPlanningRepositoriesTest extends TestCase
{
    private const string A = '0193a5e0-7b1c-7a3e-9f00-00000000000a';
    private const string B = '0193a5e0-7b1c-7a3e-9f00-00000000000b';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';

    public function testMealPlanGetAndAllReturnWhatWasAddedAndGetThrowsForAnUnknownId(): void
    {
        $repository = new InMemoryMealPlanRepository();
        $plan = MealPlan::create(new MealPlanId(self::A), new \DateTimeImmutable('2026-11-16'), new \DateTimeImmutable('2026-11-12'), new \DateTimeImmutable());
        $repository->add($plan);

        self::assertSame($plan, $repository->get(new MealPlanId(self::A)));
        self::assertSame([$plan], $repository->all());

        $this->expectException(NotFoundException::class);
        $repository->get(new MealPlanId(self::B));
    }

    public function testShoppingListIsFoundByItsPlanAndNullForAPlanWithoutOne(): void
    {
        $repository = new InMemoryShoppingListRepository();
        $list = $this->list(self::A, self::A);
        $repository->add($list);

        self::assertSame($list, $repository->findByPlan(new MealPlanId(self::A)));
        self::assertNull($repository->findByPlan(new MealPlanId(self::B)));
    }

    public function testShl01ShoppingListAddRefusesASecondListForTheSamePlan(): void
    {
        $repository = new InMemoryShoppingListRepository();
        $list = $this->list(self::A, self::A);
        $repository->add($list);
        $repository->add($list);

        $this->expectException(\LogicException::class);
        $repository->add($this->list(self::B, self::A));
    }

    private function list(string $listId, string $planId): ShoppingList
    {
        return ShoppingList::generate(new ShoppingListId($listId), new MealPlanId($planId), [
            new ProductRequirement(new ProductId(self::RICE), 'Rice', Quantity::of('1', Unit::Kilogram)),
        ], new \DateTimeImmutable());
    }
}
