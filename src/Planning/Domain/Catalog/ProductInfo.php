<?php

declare(strict_types=1);

namespace App\Planning\Domain\Catalog;

use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;

/**
 * What Planning needs to know about a product beyond its id: a name to show
 * and the category its amounts are measured in.
 */
final readonly class ProductInfo
{
    public function __construct(
        public ProductId $productId,
        public string $name,
        public MeasurementCategory $category,
    ) {
    }
}
