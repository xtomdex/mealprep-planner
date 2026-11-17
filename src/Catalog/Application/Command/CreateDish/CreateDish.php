<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateDish;

use App\Catalog\Domain\Model\IngredientLine;
use App\Shared\Application\Bus\Command;
use App\Shared\Domain\Identity\DishId;

/**
 * Adds a dish: one portion, described by its lines. Typed values only, as
 * for CreateProduct.
 */
final readonly class CreateDish implements Command
{
    /**
     * @param list<IngredientLine> $lines in recipe order
     */
    public function __construct(
        public DishId $id,
        public string $name,
        public array $lines,
    ) {
    }
}
