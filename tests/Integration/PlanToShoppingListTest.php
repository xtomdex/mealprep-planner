<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Catalog\Application\Command\CreateComponent\CreateComponent;
use App\Catalog\Application\Command\CreateDish\CreateDish;
use App\Catalog\Application\Command\CreateProduct\CreateProduct;
use App\Catalog\Application\Query\GetDish\GetDish;
use App\Catalog\Application\Query\ListComponents\ListComponents;
use App\Catalog\Application\Query\ListDishes\ListDishes;
use App\Catalog\Application\Query\ListProducts\ListProducts;
use App\Catalog\Application\View\ComponentSummaryView;
use App\Catalog\Application\View\DishSummaryView;
use App\Catalog\Application\View\DishView;
use App\Catalog\Application\View\IngredientLineView;
use App\Catalog\Application\View\ProductView;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Planning\Application\Command\AddMealPlanEntry\AddMealPlanEntry;
use App\Planning\Application\Command\CreateMealPlan\CreateMealPlan;
use App\Planning\Application\Command\FinalizeMealPlan\FinalizeMealPlan;
use App\Planning\Application\Command\GenerateShoppingList\GenerateShoppingList;
use App\Planning\Application\Query\GetMealPlan\GetMealPlan;
use App\Planning\Application\Query\GetShoppingList\GetShoppingList;
use App\Planning\Application\Query\ListMealPlans\ListMealPlans;
use App\Planning\Application\View\MealPlanEntryView;
use App\Planning\Application\View\MealPlanSummaryView;
use App\Planning\Application\View\MealPlanView;
use App\Planning\Application\View\ShoppingItemView;
use App\Planning\Application\View\ShoppingListView;
use App\Planning\Domain\Catalog\CatalogReader;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ShoppingListId;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Application\View\QuantityView;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Planning\InMemoryCatalogReader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The whole write-and-read path of the demo, through both buses as the HTTP
 * layer will use them: a catalog, a plan, its shopping list, staleness and
 * regeneration, finalization. Asking all seven queries here also proves
 * that every query handler is registered on query.bus.
 *
 * One seam is bridged by hand: Planning reads the catalog through
 * CatalogReader, and the adapter over Catalog's application layer arrives
 * with the database (D-07). Until then the reader fake is seeded with the
 * same catalog the commands created, so the Planning half runs on the data
 * the Catalog half reports, not on a different one.
 */
final class PlanToShoppingListTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string TOMATO = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string OIL = '0193a5e0-7b1c-7a3e-9f00-0000000000a3';
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';
    private const string BOWL = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string LIST = '0193a5e0-7b1c-7a3e-9f00-0000000000f1';
    private const string UNUSED_LIST = '0193a5e0-7b1c-7a3e-9f00-0000000000f2';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';
    private const string E2 = '0193a5e0-7b1c-7a3e-9f00-0000000000e2';

    private CommandBus $commands;

    private QueryBus $queries;

    private MockClock $clock;

    protected function setUp(): void
    {
        $clock = self::mockTime('2026-11-24 09:00:00 UTC');
        self::assertInstanceOf(MockClock::class, $clock);
        $this->clock = $clock;

        $this->commands = $this->service(CommandBus::class);
        $this->queries = $this->service(QueryBus::class);
    }

    public function testACatalogBecomesAPlanAndAShoppingListThatFollowsThePlan(): void
    {
        $this->createCatalog();
        $this->mirrorCatalogForPlanning();

        $plan = new MealPlanId(self::PLAN);
        $this->commands->dispatch(new CreateMealPlan($plan, new \DateTimeImmutable('2026-11-30')));
        $this->commands->dispatch(new AddMealPlanEntry($plan, new MealPlanEntryId(self::E1), new \DateTimeImmutable('2026-11-30'), new DishId(self::BOWL), 2));

        $this->commands->dispatch(new GenerateShoppingList($plan, new ShoppingListId(self::LIST)));
        self::assertFalse($this->shoppingList()->stale, 'fresh right after generation');

        $this->clock->modify('+1 hour');
        $this->commands->dispatch(new AddMealPlanEntry($plan, new MealPlanEntryId(self::E2), new \DateTimeImmutable('2026-12-01'), new DishId(self::BOWL), 1));
        self::assertTrue($this->shoppingList()->stale, 'the plan changed after the list was generated');

        $this->clock->modify('+1 hour');
        $this->commands->dispatch(new GenerateShoppingList($plan, new ShoppingListId(self::UNUSED_LIST)));
        $this->clock->modify('+1 hour');
        $this->commands->dispatch(new FinalizeMealPlan($plan));

        $planView = $this->queries->ask(new GetMealPlan($plan));
        self::assertInstanceOf(MealPlanView::class, $planView);
        self::assertSame('finalized', $planView->status);
        self::assertEquals(new \DateTimeImmutable('2026-11-24 12:00:00 UTC'), $planView->finalizedAt);
        self::assertSame(['2026-11-30', '2026-12-01'], array_map(static fn (MealPlanEntryView $entry): string => $entry->day, $planView->entries));

        self::assertEquals(
            [new MealPlanSummaryView(self::PLAN, '2026-11-30', '2026-12-06', 'finalized', 2)],
            $this->queries->ask(new ListMealPlans()),
        );

        // Three bowls: 3 × 150 g rice; 3/4 of a sauce batch of 450 g
        // tomatoes and 20 ml oil, i.e. 337.5 g → 338 g and 15 ml. Sorted by
        // name, the regenerated list keeps the id of the first one.
        self::assertEquals(new ShoppingListView(self::LIST, self::PLAN, new \DateTimeImmutable('2026-11-24 11:00:00 UTC'), false, [
            new ShoppingItemView(self::OIL, 'Olive oil', new QuantityView('15', 'ml'), 1),
            new ShoppingItemView(self::RICE, 'Rice', new QuantityView('450', 'g'), 2),
            new ShoppingItemView(self::TOMATO, 'Tomato', new QuantityView('338', 'g'), 3),
        ]), $this->shoppingList());
    }

    private function createCatalog(): void
    {
        $this->commands->dispatch(new CreateProduct(new ProductId(self::TOMATO), 'Tomato', MeasurementCategory::Weight));
        $this->commands->dispatch(new CreateProduct(new ProductId(self::RICE), 'Rice', MeasurementCategory::Weight));
        $this->commands->dispatch(new CreateProduct(new ProductId(self::OIL), 'Olive oil', MeasurementCategory::Volume));
        $this->commands->dispatch(new CreateComponent(new ComponentId(self::SAUCE), 'Tomato sauce', 4, [
            new IngredientLine(IngredientRef::product(new ProductId(self::TOMATO)), Quantity::of('450', Unit::Gram)),
            new IngredientLine(IngredientRef::product(new ProductId(self::OIL)), Quantity::of('20', Unit::Millilitre)),
        ]));
        $this->commands->dispatch(new CreateDish(new DishId(self::BOWL), 'Rice bowl', [
            new IngredientLine(IngredientRef::product(new ProductId(self::RICE)), Quantity::of('150', Unit::Gram)),
            new IngredientLine(IngredientRef::component(new ComponentId(self::SAUCE)), Quantity::of('1', Unit::Portion)),
        ]));

        self::assertEquals([
            new ProductView(self::OIL, 'Olive oil', 'volume'),
            new ProductView(self::RICE, 'Rice', 'weight'),
            new ProductView(self::TOMATO, 'Tomato', 'weight'),
        ], $this->queries->ask(new ListProducts()));
        self::assertEquals([new ComponentSummaryView(self::SAUCE, 'Tomato sauce', 4, 2)], $this->queries->ask(new ListComponents()));
        self::assertEquals([new DishSummaryView(self::BOWL, 'Rice bowl', 2)], $this->queries->ask(new ListDishes()));
        self::assertEquals(new DishView(self::BOWL, 'Rice bowl', [
            new IngredientLineView('product', self::RICE, 'Rice', new QuantityView('150', 'g')),
            new IngredientLineView('component', self::SAUCE, 'Tomato sauce', new QuantityView('1', 'portion')),
        ]), $this->queries->ask(new GetDish(new DishId(self::BOWL))));
    }

    /**
     * The same catalog, in the shape CatalogReader answers with (see the
     * class docblock); replaced by the real adapter in D-07.
     */
    private function mirrorCatalogForPlanning(): void
    {
        $reader = $this->service(CatalogReader::class);
        self::assertInstanceOf(InMemoryCatalogReader::class, $reader);
        $reader
            ->withProduct(self::TOMATO, 'Tomato')
            ->withProduct(self::RICE, 'Rice')
            ->withProduct(self::OIL, 'Olive oil', MeasurementCategory::Volume)
            ->withComponent(self::SAUCE, 'Tomato sauce', 4, [self::TOMATO => Quantity::of('450', Unit::Gram), self::OIL => Quantity::of('20', Unit::Millilitre)])
            ->withDish(self::BOWL, [self::RICE => Quantity::of('150', Unit::Gram)], [self::SAUCE => '1']);
    }

    private function shoppingList(): ShoppingListView
    {
        $view = $this->queries->ask(new GetShoppingList(new MealPlanId(self::PLAN)));
        self::assertInstanceOf(ShoppingListView::class, $view);

        return $view;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
