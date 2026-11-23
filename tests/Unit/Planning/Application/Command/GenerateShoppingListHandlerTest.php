<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Application\Command;

use App\Planning\Application\Command\GenerateShoppingList\GenerateShoppingList;
use App\Planning\Application\Command\GenerateShoppingList\GenerateShoppingListHandler;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ShoppingList;
use App\Planning\Domain\Model\ShoppingListId;
use App\Planning\Domain\Service\MealPlanDecomposer;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Planning\InMemoryCatalogReader;
use App\Tests\Support\Planning\InMemoryMealPlanRepository;
use App\Tests\Support\Planning\InMemoryShoppingListRepository;
use App\Tests\Support\Shared\ImmediateTransactionalRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class GenerateShoppingListHandlerTest extends TestCase
{
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string LIST = '0193a5e0-7b1c-7a3e-9f00-0000000000f1';
    private const string OTHER_LIST = '0193a5e0-7b1c-7a3e-9f00-0000000000f2';
    private const string BOWL = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string TOMATO = '0193a5e0-7b1c-7a3e-9f00-0000000000a3';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';
    private const string E2 = '0193a5e0-7b1c-7a3e-9f00-0000000000e2';

    private MealPlan $plan;

    private InMemoryShoppingListRepository $lists;

    private ImmediateTransactionalRunner $transaction;

    private MockClock $clock;

    private GenerateShoppingListHandler $handler;

    protected function setUp(): void
    {
        // One bowl is 150 g of rice and a quarter of a sauce batch that
        // takes 450 g of tomatoes: 112.5 g, which the list rounds up.
        $catalog = (new InMemoryCatalogReader())
            ->withProduct(self::RICE, 'Rice')
            ->withProduct(self::TOMATO, 'Tomato')
            ->withComponent(self::SAUCE, 'Tomato sauce', 4, [self::TOMATO => Quantity::of('450', Unit::Gram)])
            ->withDish(self::BOWL, [self::RICE => Quantity::of('150', Unit::Gram)], [self::SAUCE => '1']);

        $created = new \DateTimeImmutable('2026-11-12 10:00');
        $this->plan = MealPlan::create(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-16'), $created, $created);
        $plans = new InMemoryMealPlanRepository();
        $plans->add($this->plan);

        $this->lists = new InMemoryShoppingListRepository();
        $this->transaction = new ImmediateTransactionalRunner();
        $this->clock = new MockClock('2026-11-13 09:00:00');
        $this->handler = new GenerateShoppingListHandler($plans, $this->lists, new MealPlanDecomposer($catalog), $this->transaction, $this->clock);
    }

    public function testTheFirstCallCreatesTheListWithAmountsRoundedUpToWholeGrams(): void
    {
        $this->addBowls(self::E1, '2026-11-17', 1);

        ($this->handler)($this->generate(self::LIST));

        $list = $this->storedList();
        self::assertSame(self::LIST, $list->id()->value);
        self::assertSame(['Rice' => '150 g', 'Tomato' => '113 g'], self::items($list));
        self::assertEquals($this->clock->now(), $list->generatedAt());
        self::assertSame(1, $this->transaction->runs());
    }

    public function testShl01ASecondCallAfterThePlanChangedReplacesTheItemsAndKeepsTheListId(): void
    {
        $this->addBowls(self::E1, '2026-11-17', 1);
        ($this->handler)($this->generate(self::LIST));

        $this->addBowls(self::E2, '2026-11-18', 2);
        $this->clock->modify('+1 hour');
        ($this->handler)($this->generate(self::OTHER_LIST));

        $list = $this->storedList();
        self::assertSame(self::LIST, $list->id()->value, 'the existing list keeps its id');
        self::assertSame(['Rice' => '450 g', 'Tomato' => '338 g'], self::items($list));
        self::assertEquals($this->clock->now(), $list->generatedAt());
        self::assertFalse($list->isStaleFor($this->plan));
    }

    public function testAFinalizedPlanGetsAListToo(): void
    {
        $this->addBowls(self::E1, '2026-11-17', 1);
        $this->plan->finalize(new \DateTimeImmutable('2026-11-12 12:00'));

        ($this->handler)($this->generate(self::LIST));

        self::assertSame(['Rice' => '150 g', 'Tomato' => '113 g'], self::items($this->storedList()));
    }

    public function testShl02AnEmptyPlanIsRejectedAndNoListIsStored(): void
    {
        try {
            ($this->handler)($this->generate(self::LIST));
            self::fail('Expected SHL-02.');
        } catch (ValidationException $e) {
            self::assertSame('SHL-02', $e->errorCode());
        }

        self::assertNull($this->lists->findByPlan(new MealPlanId(self::PLAN)));
    }

    private function addBowls(string $entryId, string $day, int $portions): void
    {
        $this->plan->addEntry(new MealPlanEntryId($entryId), new \DateTimeImmutable($day), new DishId(self::BOWL), $portions, $this->clock->now());
    }

    private function generate(string $newListId): GenerateShoppingList
    {
        return new GenerateShoppingList(new MealPlanId(self::PLAN), new ShoppingListId($newListId));
    }

    private function storedList(): ShoppingList
    {
        $list = $this->lists->findByPlan(new MealPlanId(self::PLAN));
        self::assertNotNull($list);

        return $list;
    }

    /**
     * @return array<string, string> product name => quantity, in list order
     */
    private static function items(ShoppingList $list): array
    {
        $items = [];
        foreach ($list->items() as $item) {
            $items[$item->productName()] = (string) $item->quantity();
        }

        return $items;
    }
}
