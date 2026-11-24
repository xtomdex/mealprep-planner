<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Application\Query;

use App\Planning\Application\Query\GetShoppingList\GetShoppingList;
use App\Planning\Application\Query\GetShoppingList\GetShoppingListHandler;
use App\Planning\Application\View\ShoppingItemView;
use App\Planning\Application\View\ShoppingListView;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ProductRequirement;
use App\Planning\Domain\Model\ShoppingList;
use App\Planning\Domain\Model\ShoppingListId;
use App\Shared\Application\View\QuantityView;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Planning\InMemoryMealPlanRepository;
use App\Tests\Support\Planning\InMemoryShoppingListRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class GetShoppingListHandlerTest extends TestCase
{
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string UNKNOWN_PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000009';
    private const string LIST = '0193a5e0-7b1c-7a3e-9f00-0000000000f1';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string OIL = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';
    private const string E2 = '0193a5e0-7b1c-7a3e-9f00-0000000000e2';

    private MealPlan $plan;

    private InMemoryShoppingListRepository $lists;

    private MockClock $clock;

    private GetShoppingListHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-24 10:00:00');
        $this->plan = MealPlan::create(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-30'), $this->clock->now(), $this->clock->now());
        $this->addSoup(self::E1, '2026-11-30');
        $plans = new InMemoryMealPlanRepository();
        $plans->add($this->plan);

        $this->lists = new InMemoryShoppingListRepository();
        $this->handler = new GetShoppingListHandler($plans, $this->lists);
    }

    public function testItemsComeInPositionOrderWithNamesAndRoundedCanonicalQuantities(): void
    {
        $this->clock->modify('+1 hour');
        $this->generate();

        self::assertEquals(new ShoppingListView(self::LIST, self::PLAN, new \DateTimeImmutable('2026-11-24 11:00:00'), false, [
            new ShoppingItemView(self::RICE, 'Rice', new QuantityView('334', 'g'), 1),
            new ShoppingItemView(self::OIL, 'Olive oil', new QuantityView('500', 'ml'), 2),
        ]), $this->get());
    }

    public function testTheListIsStaleOnceAnEntryIsAddedToTheDraftAfterGeneration(): void
    {
        $this->clock->modify('+1 hour');
        $this->generate();
        self::assertFalse($this->get()->stale, 'fresh right after generation');

        $this->clock->modify('+1 hour');
        $this->addSoup(self::E2, '2026-12-01');

        self::assertTrue($this->get()->stale);
    }

    public function testAListGeneratedForAFinalizedPlanNeverGoesStaleBecauseThePlanCannotChange(): void
    {
        $this->clock->modify('+1 hour');
        $this->plan->finalize($this->clock->now());
        $this->clock->modify('+1 hour');
        $this->generate();

        try {
            $this->clock->modify('+1 hour');
            $this->addSoup(self::E2, '2026-12-01');
            self::fail('Expected PLN-04.');
        } catch (ConflictException $e) {
            self::assertSame('PLN-04', $e->errorCode());
        }

        self::assertFalse($this->get()->stale);
    }

    public function testFinalizingDoesNotRefreshAListThatWasAlreadyStale(): void
    {
        // The domain compares entry changes with generation time only;
        // finalize() is not an entry change, so it neither causes nor
        // clears staleness. The handler passes that answer through.
        $this->clock->modify('+1 hour');
        $this->generate();
        $this->clock->modify('+1 hour');
        $this->addSoup(self::E2, '2026-12-01');
        $this->plan->finalize($this->clock->now());

        self::assertTrue($this->get()->stale);
    }

    public function testAPlanWithoutAListIsNotFoundRatherThanAnEmptyList(): void
    {
        try {
            $this->get();
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('ShoppingList for MealPlan', $e->resource());
            self::assertSame(self::PLAN, $e->id());
        }
    }

    public function testAnUnknownPlanIsNotFound(): void
    {
        try {
            ($this->handler)(new GetShoppingList(new MealPlanId(self::UNKNOWN_PLAN)));
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('MealPlan', $e->resource());
        }
    }

    private function generate(): void
    {
        $this->lists->add(ShoppingList::generate(new ShoppingListId(self::LIST), $this->plan->id(), [
            new ProductRequirement(new ProductId(self::RICE), 'Rice', Quantity::of('333.33', Unit::Gram)),
            new ProductRequirement(new ProductId(self::OIL), 'Olive oil', Quantity::of('0.5', Unit::Litre)),
        ], $this->clock->now()));
    }

    private function addSoup(string $entryId, string $day): void
    {
        $this->plan->addEntry(new MealPlanEntryId($entryId), new \DateTimeImmutable($day), new DishId(self::SOUP), 1, $this->clock->now());
    }

    private function get(): ShoppingListView
    {
        return ($this->handler)(new GetShoppingList(new MealPlanId(self::PLAN)));
    }
}
