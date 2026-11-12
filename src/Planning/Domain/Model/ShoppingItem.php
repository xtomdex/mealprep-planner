<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;

/**
 * One line of a shopping list. Like a recipe line it has no id of its own:
 * owner and position identify it, and the whole set is replaced at once.
 *
 * @internal created only by ShoppingList; pass ProductRequirement instead
 */
final class ShoppingItem
{
    /**
     * @param int      $position 1-based, in the order the requirements were given
     * @param Quantity $quantity canonical and whole, as normalised by ShoppingList
     */
    public function __construct(
        private readonly ShoppingList $shoppingList,
        private readonly int $position,
        private readonly ProductId $productId,
        private readonly string $productName,
        private readonly Quantity $quantity,
    ) {
    }

    public function shoppingList(): ShoppingList
    {
        return $this->shoppingList;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function productName(): string
    {
        return $this->productName;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }
}
