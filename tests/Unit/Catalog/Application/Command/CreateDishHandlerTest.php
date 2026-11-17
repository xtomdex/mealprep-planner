<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\CreateDish\CreateDish;
use App\Catalog\Application\Command\CreateDish\CreateDishHandler;
use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\DishIngredient;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Service\IngredientReferenceGuard;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Catalog\InMemoryComponentRepository;
use App\Tests\Support\Catalog\InMemoryDishRepository;
use App\Tests\Support\Catalog\InMemoryProductRepository;
use App\Tests\Support\Shared\ImmediateTransactionalRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreateDishHandlerTest extends TestCase
{
    private const string BOWL = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';

    private InMemoryDishRepository $dishes;

    private ImmediateTransactionalRunner $transaction;

    private MockClock $clock;

    private CreateDishHandler $handler;

    protected function setUp(): void
    {
        $products = new InMemoryProductRepository();
        $products->add(Product::create(new ProductId(self::RICE), 'Rice', MeasurementCategory::Weight, new \DateTimeImmutable()));
        $components = new InMemoryComponentRepository();
        $components->add(Component::create(new ComponentId(self::SAUCE), 'Tomato sauce', 4, [
            new IngredientLine(IngredientRef::product(new ProductId(self::RICE)), Quantity::of('1', Unit::Gram)),
        ], new \DateTimeImmutable()));

        $this->dishes = new InMemoryDishRepository();
        $this->transaction = new ImmediateTransactionalRunner();
        $this->clock = new MockClock('2026-11-17 10:00:00');
        $this->handler = new CreateDishHandler($this->dishes, new IngredientReferenceGuard($products, $components), $this->transaction, $this->clock);
    }

    public function testStoresTheDishWithProductAndComponentLines(): void
    {
        ($this->handler)(new CreateDish(new DishId(self::BOWL), 'Rice bowl', [
            new IngredientLine(IngredientRef::product(new ProductId(self::RICE)), Quantity::of('150', Unit::Gram)),
            new IngredientLine(IngredientRef::component(new ComponentId(self::SAUCE)), Quantity::of('0.5', Unit::Portion)),
        ]));

        $bowl = $this->dishes->get(new DishId(self::BOWL));
        self::assertSame('Rice bowl', $bowl->name());
        self::assertSame(['150 g', '0.5 portion'], array_map(static fn (DishIngredient $i): string => (string) $i->quantity(), $bowl->ingredients()));
        self::assertEquals($this->clock->now(), $bowl->createdAt());
        self::assertSame(1, $this->transaction->runs());
    }

    public function testIng03AComponentLineNotInPortionsStopsCreationAndNothingIsStored(): void
    {
        try {
            ($this->handler)(new CreateDish(new DishId(self::BOWL), 'Rice bowl', [
                new IngredientLine(IngredientRef::component(new ComponentId(self::SAUCE)), Quantity::of('50', Unit::Gram)),
            ]));
            self::fail('Expected ING-03.');
        } catch (ValidationException $e) {
            self::assertSame('ING-03', $e->errorCode());
        }

        self::assertSame([], $this->dishes->all());
    }
}
