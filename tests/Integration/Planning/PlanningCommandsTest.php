<?php

declare(strict_types=1);

namespace App\Tests\Integration\Planning;

use App\Planning\Application\Command\AddMealPlanEntry\AddMealPlanEntry;
use App\Planning\Application\Command\CreateMealPlan\CreateMealPlan;
use App\Planning\Application\Command\FinalizeMealPlan\FinalizeMealPlan;
use App\Planning\Application\Command\GenerateShoppingList\GenerateShoppingList;
use App\Planning\Application\Command\RemoveMealPlanEntry\RemoveMealPlanEntry;
use App\Planning\Domain\Catalog\CatalogReader;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\MealPlanStatus;
use App\Planning\Domain\Model\ShoppingListId;
use App\Planning\Domain\Repository\MealPlanRepository;
use App\Planning\Domain\Repository\ShoppingListRepository;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Planning\InMemoryCatalogReader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Proves that the five Planning handlers are registered on command.bus and
 * that their dependencies resolve, the decomposer included. As in the
 * Catalog test, the ports point at the in-memory fakes in this environment
 * (see services.yaml); the catalog is seeded through the reader fake, since
 * Planning reads the catalog only through that port.
 */
final class PlanningCommandsTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string LIST = '0193a5e0-7b1c-7a3e-9f00-0000000000f1';
    private const string BOWL = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d2';
    private const string UNKNOWN_DISH = '0193a5e0-7b1c-7a3e-9f00-0000000000d9';
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string TOMATO = '0193a5e0-7b1c-7a3e-9f00-0000000000a3';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';
    private const string E2 = '0193a5e0-7b1c-7a3e-9f00-0000000000e2';

    protected function setUp(): void
    {
        self::mockTime('2026-11-13 09:00:00 UTC');

        $catalog = $this->service(CatalogReader::class);
        self::assertInstanceOf(InMemoryCatalogReader::class, $catalog);
        $catalog
            ->withProduct(self::RICE, 'Rice')
            ->withProduct(self::TOMATO, 'Tomato')
            ->withComponent(self::SAUCE, 'Tomato sauce', 4, [self::TOMATO => Quantity::of('450', Unit::Gram)])
            ->withDish(self::BOWL, [self::RICE => Quantity::of('150', Unit::Gram)], [self::SAUCE => '1'])
            ->withDish(self::SOUP, [self::TOMATO => Quantity::of('300', Unit::Gram)]);
    }

    public function testAPlanGoesFromDraftToAShoppingListThroughTheCommandBus(): void
    {
        $bus = $this->service(CommandBus::class);
        $plan = new MealPlanId(self::PLAN);

        $bus->dispatch(new CreateMealPlan($plan, new \DateTimeImmutable('2026-11-16')));
        $bus->dispatch(new AddMealPlanEntry($plan, new MealPlanEntryId(self::E1), new \DateTimeImmutable('2026-11-17'), new DishId(self::BOWL), 3));
        $bus->dispatch(new AddMealPlanEntry($plan, new MealPlanEntryId(self::E2), new \DateTimeImmutable('2026-11-18'), new DishId(self::SOUP), 1));
        $bus->dispatch(new RemoveMealPlanEntry($plan, new MealPlanEntryId(self::E2)));
        $bus->dispatch(new FinalizeMealPlan($plan));
        $bus->dispatch(new GenerateShoppingList($plan, new ShoppingListId(self::LIST)));

        self::assertSame(MealPlanStatus::Finalized, $this->service(MealPlanRepository::class)->get($plan)->status());

        $list = $this->service(ShoppingListRepository::class)->findByPlan($plan);
        self::assertNotNull($list);
        self::assertSame(self::LIST, $list->id()->value);
        self::assertSame('2026-11-13 09:00:00', $list->generatedAt()->format('Y-m-d H:i:s'), 'the handlers take time from the container clock');
        $items = [];
        foreach ($list->items() as $item) {
            $items[$item->productName()] = (string) $item->quantity();
        }
        // Three bowls take 337.5 g of tomatoes; the soup was removed.
        self::assertSame(['Rice' => '450 g', 'Tomato' => '338 g'], $items);
    }

    public function testPln07AnUnknownDishCrossesTheBusUnwrapped(): void
    {
        $bus = $this->service(CommandBus::class);
        $plan = new MealPlanId(self::PLAN);
        $bus->dispatch(new CreateMealPlan($plan, new \DateTimeImmutable('2026-11-16')));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('does not exist');

        $bus->dispatch(new AddMealPlanEntry($plan, new MealPlanEntryId(self::E1), new \DateTimeImmutable('2026-11-17'), new DishId(self::UNKNOWN_DISH), 1));
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
