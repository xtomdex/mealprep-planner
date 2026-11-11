<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\DishId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * One portion of something that is eaten, assembled from components and/or
 * products: "chicken with rice" is a portion of cooked rice, a portion of
 * grilled chicken and 10 ml of sauce. A dish is the end of the chain and is
 * never an ingredient of anything, which is why it has no yield: its lines
 * already describe one portion.
 *
 * As with Component, the lines' references are checked against other
 * aggregates by IngredientReferenceGuard, not here.
 */
final class Dish
{
    /** @var Collection<int, DishIngredient> */
    private Collection $ingredients;

    private \DateTimeImmutable $updatedAt;

    private function __construct(
        private readonly DishId $id,
        private string $name,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->ingredients = new ArrayCollection();
        $this->updatedAt = $createdAt;
    }

    /**
     * DSH-01: the name is non-empty after trimming and at most 120 characters.
     * DSH-02: there is at least one ingredient line.
     * DSH-03: the same ingredient does not appear on two lines.
     *
     * @param list<IngredientLine> $lines positions are assigned in this order, starting at 1
     *
     * @throws ValidationException DSH-01, DSH-02, DSH-03
     */
    public static function create(DishId $id, string $name, array $lines, \DateTimeImmutable $now): self
    {
        $name = CatalogName::normalize($name, 'DSH-01');
        Composition::assertValid($lines, 'DSH-02', 'DSH-03');

        $dish = new self($id, $name, $now);
        foreach ($lines as $i => $line) {
            $dish->ingredients->add(new DishIngredient($dish, $i + 1, $line->ref, $line->quantity));
        }

        return $dish;
    }

    public function id(): DishId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<DishIngredient>
     */
    public function ingredients(): array
    {
        return array_values($this->ingredients->toArray());
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
