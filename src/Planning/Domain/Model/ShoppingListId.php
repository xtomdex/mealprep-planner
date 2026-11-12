<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

use App\Shared\Domain\Identity\Uuid;

/**
 * Identifies a shopping list. Only planning refers to lists, so the id lives
 * in the module instead of the shared kernel.
 */
final readonly class ShoppingListId extends Uuid
{
}
