<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\Command\CreateComponent\CreateComponent;
use App\Catalog\Application\Command\CreateDish\CreateDish;
use App\Catalog\Application\Command\CreateProduct\CreateProduct;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Repository\ComponentRepository;
use App\Catalog\Domain\Repository\DishRepository;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Proves that the Catalog handlers are registered on command.bus by their
 * marker interface alone. The repository ports point at the in-memory fakes
 * in the test environment (see services.yaml), so no database is needed;
 * the handlers' own behaviour is covered by their unit tests.
 */
final class CatalogCommandsTest extends KernelTestCase
{
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';
    private const string BOWL = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';

    public function testProductComponentAndDishAreStoredThroughTheCommandBus(): void
    {
        $bus = $this->service(CommandBus::class);

        $bus->dispatch(new CreateProduct(new ProductId(self::RICE), 'Rice', MeasurementCategory::Weight));
        $bus->dispatch(new CreateComponent(new ComponentId(self::SAUCE), 'Rice sauce', 2, [
            new IngredientLine(IngredientRef::product(new ProductId(self::RICE)), Quantity::of('100', Unit::Gram)),
        ]));
        $bus->dispatch(new CreateDish(new DishId(self::BOWL), 'Rice bowl', [
            new IngredientLine(IngredientRef::component(new ComponentId(self::SAUCE)), Quantity::of('1', Unit::Portion)),
        ]));

        self::assertSame('Rice', $this->service(ProductRepository::class)->get(new ProductId(self::RICE))->name());
        self::assertSame('Rice sauce', $this->service(ComponentRepository::class)->get(new ComponentId(self::SAUCE))->name());
        self::assertSame('Rice bowl', $this->service(DishRepository::class)->get(new DishId(self::BOWL))->name());
    }

    public function testIng01ARuleViolationCrossesTheBusUnwrapped(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('the product does not exist');

        $this->service(CommandBus::class)->dispatch(new CreateDish(new DishId(self::BOWL), 'Rice bowl', [
            new IngredientLine(IngredientRef::product(new ProductId(self::RICE)), Quantity::of('150', Unit::Gram)),
        ]));
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
