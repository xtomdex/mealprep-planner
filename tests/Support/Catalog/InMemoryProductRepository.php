<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\ProductId;

/**
 * A working ProductRepository backed by an array. Tests use it instead of
 * mocks so that they exercise the port's contract, not a list of expected
 * calls, and keep passing when the code under test changes how it queries.
 */
final class InMemoryProductRepository implements ProductRepository
{
    /** @var array<string, Product> keyed by id value */
    private array $products = [];

    public function get(ProductId $id): Product
    {
        return $this->products[$id->value] ?? throw new NotFoundException('Product', $id->value);
    }

    public function findByIds(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            if (isset($this->products[$id->value])) {
                $found[$id->value] = $this->products[$id->value];
            }
        }

        return array_values($found);
    }

    public function all(): array
    {
        return array_values($this->products);
    }

    public function add(Product $product): void
    {
        $this->products[$product->id()->value] = $product;
    }
}
