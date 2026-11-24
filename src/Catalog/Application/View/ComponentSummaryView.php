<?php

declare(strict_types=1);

namespace App\Catalog\Application\View;

use App\Catalog\Domain\Model\Component;

/**
 * A component in a list: a line count instead of the lines, so a list
 * answer stays small and does not need the names of every ingredient.
 */
final readonly class ComponentSummaryView
{
    public function __construct(
        public string $id,
        public string $name,
        public int $yieldPortions,
        public int $lineCount,
    ) {
    }

    public static function of(Component $component): self
    {
        return new self($component->id()->value, $component->name(), $component->yieldPortions(), \count($component->ingredients()));
    }
}
