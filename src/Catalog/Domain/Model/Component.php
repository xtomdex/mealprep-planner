<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ComponentId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Something cooked from products and other components: a sauce, cooked rice,
 * a spice mix. Its recipe yields yieldPortions portions, and its ingredient
 * lines are a bill of materials for that whole batch.
 *
 * Whether the lines point at things that exist, in sensible units and
 * without cycles, needs other aggregates and is checked by
 * IngredientReferenceGuard before the component is created.
 */
final class Component
{
    /** @var Collection<int, ComponentIngredient> */
    private Collection $ingredients;

    private \DateTimeImmutable $updatedAt;

    private function __construct(
        private readonly ComponentId $id,
        private string $name,
        private int $yieldPortions,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->ingredients = new ArrayCollection();
        $this->updatedAt = $createdAt;
    }

    /**
     * CMP-01: the name is non-empty after trimming and at most 120 characters.
     * CMP-02: there is at least one ingredient line.
     * CMP-03: the recipe yields at least one portion.
     * CMP-04: the same ingredient does not appear on two lines.
     *
     * @param list<IngredientLine> $lines positions are assigned in this order, starting at 1
     *
     * @throws ValidationException CMP-01, CMP-02, CMP-03, CMP-04
     */
    public static function create(ComponentId $id, string $name, int $yieldPortions, array $lines, \DateTimeImmutable $now): self
    {
        $name = CatalogName::normalize($name, 'CMP-01');

        if ($yieldPortions < 1) {
            throw new ValidationException('CMP-03', \sprintf('A recipe must yield at least one portion, got %d.', $yieldPortions), 'yieldPortions');
        }

        Composition::assertValid($lines, 'CMP-02', 'CMP-04');

        $component = new self($id, $name, $yieldPortions, $now);
        foreach ($lines as $i => $line) {
            $component->ingredients->add(new ComponentIngredient($component, $i + 1, $line->ref, $line->quantity));
        }

        return $component;
    }

    public function id(): ComponentId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function yieldPortions(): int
    {
        return $this->yieldPortions;
    }

    /**
     * A plain list, not the Collection: callers cannot add or remove lines
     * behind the aggregate's back.
     *
     * @return list<ComponentIngredient>
     */
    public function ingredients(): array
    {
        return array_values($this->ingredients->toArray());
    }

    /**
     * Whether any line points at another component. Only a flat component
     * may be used inside another one, which keeps nesting at two levels.
     */
    public function hasComponentLines(): bool
    {
        return $this->ingredients->exists(static fn (int $key, ComponentIngredient $line): bool => $line->ref()->isComponent());
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
