<?php

declare(strict_types=1);

namespace App\Planning\Infrastructure\Persistence\Type;

use App\Planning\Domain\Model\ShoppingListId;
use App\Shared\Infrastructure\Persistence\Type\UuidType;

/**
 * @extends UuidType<ShoppingListId>
 */
final class ShoppingListIdType extends UuidType
{
    public const string NAME = 'shopping_list_id';

    protected function idClass(): string
    {
        return ShoppingListId::class;
    }
}
