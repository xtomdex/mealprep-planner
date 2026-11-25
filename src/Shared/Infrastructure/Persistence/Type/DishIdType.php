<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Type;

use App\Shared\Domain\Identity\DishId;

/**
 * @extends UuidType<DishId>
 */
final class DishIdType extends UuidType
{
    public const string NAME = 'dish_id';

    protected function idClass(): string
    {
        return DishId::class;
    }
}
