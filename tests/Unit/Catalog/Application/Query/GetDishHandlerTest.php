<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\GetDish\GetDish;
use App\Catalog\Application\Query\GetDish\GetDishHandler;
use App\Catalog\Application\View\DishView;
use App\Catalog\Application\View\IngredientLineView;
use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\Product;
use App\Shared\Application\View\QuantityView;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Catalog\InMemoryComponentRepository;
use App\Tests\Support\Catalog\InMemoryDishRepository;
use App\Tests\Support\Catalog\InMemoryProductRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class GetDishHandlerTest extends TestCase
{
    private const string BOWL = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string UNKNOWN_DISH = '0193a5e0-7b1c-7a3e-9f00-0000000000d9';
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';
    private const string PICKLES = '0193a5e0-7b1c-7a3e-9f00-0000000000c2';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string CHICKEN = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string MISSING_PRODUCT = '0193a5e0-7b1c-7a3e-9f00-0000000000a9';

    private InMemoryProductRepository $products;

    private InMemoryComponentRepository $components;

    private InMemoryDishRepository $dishes;

    private MockClock $clock;

    private GetDishHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-24 10:00:00');
        $now = $this->clock->now();

        $this->products = new InMemoryProductRepository();
        $this->products->add(Product::create(new ProductId(self::RICE), 'Rice', MeasurementCategory::Weight, $now));
        $this->products->add(Product::create(new ProductId(self::CHICKEN), 'Chicken breast', MeasurementCategory::Weight, $now));

        $this->components = new InMemoryComponentRepository();
        foreach ([self::SAUCE => 'Tomato sauce', self::PICKLES => 'Pickled onion'] as $id => $name) {
            $this->components->add(Component::create(new ComponentId($id), $name, 4, [self::line(IngredientRef::product(new ProductId(self::RICE)), '1', Unit::Gram)], $now));
        }

        $this->dishes = new InMemoryDishRepository();
        $this->handler = new GetDishHandler($this->dishes, $this->products, $this->components);
    }

    public function testTheDishComesWithItsLinesInRecipeOrderNamedAndMeasured(): void
    {
        $this->addBowl(
            self::line(IngredientRef::product(new ProductId(self::RICE)), '150', Unit::Gram),
            self::line(IngredientRef::component(new ComponentId(self::SAUCE)), '0.5', Unit::Portion),
            self::line(IngredientRef::product(new ProductId(self::CHICKEN)), '0.12', Unit::Kilogram),
        );

        $view = ($this->handler)(new GetDish(new DishId(self::BOWL)));

        self::assertEquals(new DishView(self::BOWL, 'Rice bowl', [
            new IngredientLineView('product', self::RICE, 'Rice', new QuantityView('150', 'g')),
            new IngredientLineView('component', self::SAUCE, 'Tomato sauce', new QuantityView('0.5', 'portion')),
            new IngredientLineView('product', self::CHICKEN, 'Chicken breast', new QuantityView('0.12', 'kg')),
        ]), $view);
    }

    public function testNamesAreLoadedWithOneBatchCallPerKindWhateverTheNumberOfLines(): void
    {
        $this->addBowl(
            self::line(IngredientRef::product(new ProductId(self::RICE)), '150', Unit::Gram),
            self::line(IngredientRef::component(new ComponentId(self::SAUCE)), '0.5', Unit::Portion),
            self::line(IngredientRef::product(new ProductId(self::CHICKEN)), '120', Unit::Gram),
            self::line(IngredientRef::component(new ComponentId(self::PICKLES)), '0.25', Unit::Portion),
        );

        ($this->handler)(new GetDish(new DishId(self::BOWL)));

        self::assertSame(1, $this->products->findByIdsCalls());
        self::assertSame(1, $this->components->findByIdsCalls());
    }

    public function testAKindTheDishDoesNotUseIsNotAskedFor(): void
    {
        $this->addBowl(self::line(IngredientRef::product(new ProductId(self::RICE)), '150', Unit::Gram));

        ($this->handler)(new GetDish(new DishId(self::BOWL)));

        self::assertSame(0, $this->components->findByIdsCalls());
    }

    public function testAnUnknownDishIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        ($this->handler)(new GetDish(new DishId(self::UNKNOWN_DISH)));
    }

    public function testALineWhoseIngredientIsGoneIsABrokenCatalogNotANotFound(): void
    {
        // Dish::create does not look at other aggregates, so a dangling line
        // can be built here; in the application the guard and the foreign
        // keys rule it out.
        $this->addBowl(self::line(IngredientRef::product(new ProductId(self::MISSING_PRODUCT)), '150', Unit::Gram));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('uses product '.self::MISSING_PRODUCT);

        ($this->handler)(new GetDish(new DishId(self::BOWL)));
    }

    private function addBowl(IngredientLine ...$lines): void
    {
        $this->dishes->add(Dish::create(new DishId(self::BOWL), 'Rice bowl', array_values($lines), $this->clock->now()));
    }

    private static function line(IngredientRef $ref, string $amount, Unit $unit): IngredientLine
    {
        return new IngredientLine($ref, Quantity::of($amount, $unit));
    }
}
