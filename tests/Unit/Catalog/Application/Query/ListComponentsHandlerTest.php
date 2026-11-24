<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\ListComponents\ListComponents;
use App\Catalog\Application\Query\ListComponents\ListComponentsHandler;
use App\Catalog\Application\View\ComponentSummaryView;
use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Catalog\InMemoryComponentRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ListComponentsHandlerTest extends TestCase
{
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';
    private const string SAUCE_1 = '0193a5e0-7b1c-7a3e-9f00-0000000000c2';
    private const string SAUCE_2 = '0193a5e0-7b1c-7a3e-9f00-0000000000c3';
    private const string GRAIN = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string TOMATO = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string ONION = '0193a5e0-7b1c-7a3e-9f00-0000000000a3';

    private InMemoryComponentRepository $components;

    private MockClock $clock;

    private ListComponentsHandler $handler;

    protected function setUp(): void
    {
        $this->components = new InMemoryComponentRepository();
        $this->clock = new MockClock('2026-11-24 10:00:00');
        $this->handler = new ListComponentsHandler($this->components);
    }

    public function testComponentsComeBackByNameThenByIdWithTheirYieldAndLineCount(): void
    {
        // Component names are not unique: two "Tomato sauce" are told apart by id.
        $this->add(self::SAUCE_2, 'tomato sauce', 4, self::TOMATO);
        $this->add(self::RICE, 'Cooked rice', 2, self::GRAIN);
        $this->add(self::SAUCE_1, 'Tomato sauce', 6, self::TOMATO, self::ONION);

        self::assertEquals([
            new ComponentSummaryView(self::RICE, 'Cooked rice', 2, 1),
            new ComponentSummaryView(self::SAUCE_1, 'Tomato sauce', 6, 2),
            new ComponentSummaryView(self::SAUCE_2, 'tomato sauce', 4, 1),
        ], ($this->handler)(new ListComponents()));
    }

    public function testNoComponentsGiveAnEmptyList(): void
    {
        self::assertSame([], ($this->handler)(new ListComponents()));
    }

    private function add(string $id, string $name, int $yieldPortions, string ...$productIds): void
    {
        $lines = array_map(static fn (string $productId): IngredientLine => new IngredientLine(IngredientRef::product(new ProductId($productId)), Quantity::of('100', Unit::Gram)), $productIds);
        $this->components->add(Component::create(new ComponentId($id), $name, $yieldPortions, array_values($lines), $this->clock->now()));
    }
}
