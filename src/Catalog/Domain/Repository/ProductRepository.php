<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\Product;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\ProductId;

/**
 * Port: the domain says what it needs, the infrastructure decides how.
 */
interface ProductRepository
{
    /**
     * @throws NotFoundException when there is no product with this id
     */
    public function get(ProductId $id): Product;

    /**
     * Loads many products in one go, so that validating a recipe costs one
     * query instead of one per line. Unknown ids are skipped, not reported:
     * the caller compares the result with what it asked for. The order of
     * the result is not defined.
     *
     * @param list<ProductId> $ids
     *
     * @return list<Product>
     */
    public function findByIds(array $ids): array;

    /**
     * @return list<Product>
     */
    public function all(): array;

    public function add(Product $product): void;
}
