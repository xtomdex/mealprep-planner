<?php

declare(strict_types=1);

namespace App\Catalog\Application\View;

use App\Catalog\Domain\Model\Product;

/**
 * A product as callers see it (see QueryHandler for why views).
 */
final readonly class ProductView
{
    /**
     * @param string $measurementCategory weight, volume, count or portion
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $measurementCategory,
    ) {
    }

    public static function of(Product $product): self
    {
        return new self($product->id()->value, $product->name(), $product->measurementCategory()->value);
    }
}
