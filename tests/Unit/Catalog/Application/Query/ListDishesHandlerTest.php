<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\ListDishes\ListDishes;
use App\Catalog\Application\Query\ListDishes\ListDishesHandler;
use App\Catalog\Application\View\DishSummaryView;
use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Catalog\InMemoryDishRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ListDishesHandlerTest extends TestCase
{
    private const string BOWL = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d2';
    private const string SALAD = '0193a5e0-7b1c-7a3e-9f00-0000000000d3';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string TOMATO = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';

    private InMemoryDishRepository $dishes;

    private MockClock $clock;

    private ListDishesHandler $handler;

    protected function setUp(): void
    {
        $this->dishes = new InMemoryDishRepository();
        $this->clock = new MockClock('2026-11-24 10:00:00');
        $this->handler = new ListDishesHandler($this->dishes);
    }

    public function testDishesComeBackSortedByNameIgnoringCaseWithTheirLineCount(): void
    {
        $this->add(self::SOUP, 'tomato soup', IngredientRef::product(new ProductId(self::TOMATO)));
        $this->add(self::BOWL, 'Rice bowl', IngredientRef::product(new ProductId(self::RICE)), IngredientRef::component(new ComponentId(self::SAUCE)));
        $this->add(self::SALAD, 'Salad', IngredientRef::product(new ProductId(self::TOMATO)));

        self::assertEquals([
            new DishSummaryView(self::BOWL, 'Rice bowl', 2),
            new DishSummaryView(self::SALAD, 'Salad', 1),
            new DishSummaryView(self::SOUP, 'tomato soup', 1),
        ], ($this->handler)(new ListDishes()));
    }

    public function testNoDishesGiveAnEmptyList(): void
    {
        self::assertSame([], ($this->handler)(new ListDishes()));
    }

    private function add(string $id, string $name, IngredientRef ...$refs): void
    {
        $lines = array_map(static fn (IngredientRef $ref): IngredientLine => new IngredientLine($ref, $ref->isProduct() ? Quantity::of('100', Unit::Gram) : Quantity::of('1', Unit::Portion)), $refs);
        $this->dishes->add(Dish::create(new DishId($id), $name, array_values($lines), $this->clock->now()));
    }
}
