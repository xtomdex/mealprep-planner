<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateComponent;

use App\Catalog\Domain\Model\IngredientLine;
use App\Shared\Application\Bus\Command;
use App\Shared\Domain\Identity\ComponentId;

/**
 * Adds a component (a sauce, cooked rice) with its bill of materials.
 * Typed values only, as for CreateProduct.
 */
final readonly class CreateComponent implements Command
{
    /**
     * @param list<IngredientLine> $lines in recipe order
     */
    public function __construct(
        public ComponentId $id,
        public string $name,
        public int $yieldPortions,
        public array $lines,
    ) {
    }
}
