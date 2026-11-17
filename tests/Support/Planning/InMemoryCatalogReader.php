<?php

declare(strict_types=1);

namespace App\Tests\Support\Planning;

use App\Planning\Domain\Catalog\CatalogReader;
use App\Planning\Domain\Catalog\ComponentBom;
use App\Planning\Domain\Catalog\ComponentLine;
use App\Planning\Domain\Catalog\DishComposition;
use App\Planning\Domain\Catalog\ProductInfo;
use App\Planning\Domain\Catalog\ProductLine;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Identity\Uuid;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;

/**
 * A working CatalogReader backed by arrays, used instead of mocks.
 *
 * Results come back in registration order, not in the order of the ids asked
 * for, so a caller that relies on the order of the answer fails in tests.
 * Every call is recorded by method name, which lets a test check how many
 * round trips a use of the port costs.
 */
final class InMemoryCatalogReader implements CatalogReader
{
    /** @var array<string, DishComposition> */
    private array $dishes = [];

    /** @var array<string, ComponentBom> */
    private array $components = [];

    /** @var array<string, ProductInfo> */
    private array $products = [];

    /** @var list<string> */
    private array $calls = [];

    public function withProduct(string $id, string $name, MeasurementCategory $category = MeasurementCategory::Weight): self
    {
        $this->products[$id] = new ProductInfo(new ProductId($id), $name, $category);

        return $this;
    }

    /**
     * @param array<string, Quantity> $products   product id => amount in one portion of the dish
     * @param array<string, string>   $components component id => portions of it in one portion of the dish
     */
    public function withDish(string $id, array $products = [], array $components = []): self
    {
        $this->dishes[$id] = new DishComposition(new DishId($id), self::productLines($products), self::componentLines($components));

        return $this;
    }

    /**
     * @param array<string, Quantity> $products   product id => amount used by one run of the recipe
     * @param array<string, string>   $components component id => portions used by one run of the recipe
     */
    public function withComponent(string $id, string $name, int $yieldPortions, array $products = [], array $components = []): self
    {
        $this->components[$id] = new ComponentBom(new ComponentId($id), $name, $yieldPortions, self::productLines($products), self::componentLines($components));

        return $this;
    }

    /**
     * @return list<string> method names, in call order
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function dishCompositions(array $ids): array
    {
        $this->calls[] = 'dishCompositions';

        return self::pick($this->dishes, $ids);
    }

    public function componentBoms(array $ids): array
    {
        $this->calls[] = 'componentBoms';

        return self::pick($this->components, $ids);
    }

    public function products(array $ids): array
    {
        $this->calls[] = 'products';

        return self::pick($this->products, $ids);
    }

    /**
     * @template T
     *
     * @param array<string, T> $known
     * @param list<Uuid>       $ids
     *
     * @return list<T>
     */
    private static function pick(array $known, array $ids): array
    {
        $wanted = array_flip(array_map(static fn (Uuid $id): string => $id->value, $ids));

        return array_values(array_filter($known, static fn (string $key): bool => isset($wanted[$key]), \ARRAY_FILTER_USE_KEY));
    }

    /**
     * @param array<string, Quantity> $products
     *
     * @return list<ProductLine>
     */
    private static function productLines(array $products): array
    {
        $lines = [];
        foreach ($products as $id => $quantity) {
            $lines[] = new ProductLine(new ProductId($id), $quantity);
        }

        return $lines;
    }

    /**
     * @param array<string, string> $components
     *
     * @return list<ComponentLine>
     */
    private static function componentLines(array $components): array
    {
        $lines = [];
        foreach ($components as $id => $portions) {
            $lines[] = new ComponentLine(new ComponentId($id), Quantity::of($portions, Unit::Portion));
        }

        return $lines;
    }
}
