<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Type;

use App\Shared\Domain\Identity\ProductId;

/**
 * @extends UuidType<ProductId>
 */
final class ProductIdType extends UuidType
{
    public const string NAME = 'product_id';

    protected function idClass(): string
    {
        return ProductId::class;
    }
}
