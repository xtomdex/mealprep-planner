<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

use App\Shared\Domain\Exception\ValidationException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * What to buy for one meal plan, as a snapshot taken at generatedAt: it does
 * not follow later changes to the plan by itself.
 *
 * Every amount is stored in the canonical unit and rounded up to a whole
 * number (333.33 g → 334 g, 1.2 kg → 1200 g). The aggregate does it, not
 * the caller, so no path can produce a list that asks for a third of a gram.
 *
 * SHL-01 (one list per plan) is deliberately not checked here: an aggregate
 * cannot see its siblings, and a check-then-insert would race. The
 * repository looks the list up by plan, and a UNIQUE constraint on the plan
 * id in the database settles the rest.
 */
final class ShoppingList
{
    /** @var Collection<int, ShoppingItem> */
    private Collection $items;

    private function __construct(
        private readonly ShoppingListId $id,
        private readonly MealPlanId $mealPlanId,
        private \DateTimeImmutable $generatedAt,
    ) {
        $this->items = new ArrayCollection();
    }

    /**
     * SHL-02: there is at least one requirement; an empty list is never
     * produced silently.
     * SHL-03: one item per product, so the same product id twice is refused.
     * Summing is the decomposer's job; a repeat here means it did not.
     *
     * @param list<ProductRequirement> $requirements items keep this order
     *
     * @throws ValidationException SHL-02, SHL-03
     */
    public static function generate(ShoppingListId $id, MealPlanId $mealPlanId, array $requirements, \DateTimeImmutable $now): self
    {
        self::assertValid($requirements);

        $list = new self($id, $mealPlanId, $now);
        $list->fill($requirements);

        return $list;
    }

    /**
     * Regenerates the list in place after the plan changed. The same rules
     * as generate() apply and are checked before anything is touched, so a
     * rejected call leaves the old items intact. Id and plan stay; the list
     * is a fresh snapshot as of $now.
     *
     * @param list<ProductRequirement> $requirements
     *
     * @throws ValidationException SHL-02, SHL-03
     */
    public function replaceItems(array $requirements, \DateTimeImmutable $now): void
    {
        self::assertValid($requirements);

        $this->items->clear();
        $this->fill($requirements);
        $this->generatedAt = $now;
    }

    /**
     * Whether the plan's entries changed after this list was generated.
     * MealPlan moves updatedAt on entry changes only, so one comparison
     * covers drafts and finalized plans alike.
     *
     * Asking about another plan's list is a bug in the caller, not a state
     * the user can cause, hence a LogicException instead of a domain error.
     */
    public function isStaleFor(MealPlan $plan): bool
    {
        if (!$plan->id()->equals($this->mealPlanId)) {
            throw new \LogicException(\sprintf('Shopping list %s belongs to plan %s, not %s.', $this->id, $this->mealPlanId, $plan->id()));
        }

        return $plan->updatedAt() > $this->generatedAt;
    }

    public function id(): ShoppingListId
    {
        return $this->id;
    }

    public function mealPlanId(): MealPlanId
    {
        return $this->mealPlanId;
    }

    public function generatedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /**
     * In the order the requirements were given. A plain list, not the
     * Collection: callers cannot change the items behind the aggregate.
     *
     * @return list<ShoppingItem>
     */
    public function items(): array
    {
        return array_values($this->items->toArray());
    }

    /**
     * @param list<ProductRequirement> $requirements
     *
     * @throws ValidationException SHL-02, SHL-03
     */
    private static function assertValid(array $requirements): void
    {
        if ([] === $requirements) {
            throw new ValidationException('SHL-02', 'A shopping list needs at least one item.', 'items');
        }

        $seen = [];
        foreach ($requirements as $i => $requirement) {
            $key = $requirement->productId->value;
            if (isset($seen[$key])) {
                throw new ValidationException('SHL-03', \sprintf('Item %d repeats the product of item %d (product %s).', $i + 1, $seen[$key], $key), 'items');
            }
            $seen[$key] = $i + 1;
        }
    }

    /**
     * @param list<ProductRequirement> $requirements already validated
     */
    private function fill(array $requirements): void
    {
        foreach ($requirements as $i => $requirement) {
            $quantity = $requirement->quantity->toCanonical()->roundUpToWhole();
            $this->items->add(new ShoppingItem($this, $i + 1, $requirement->productId, $requirement->productName, $quantity));
        }
    }
}
