<?php

declare(strict_types=1);

namespace App\Planning\Application\Query\GetShoppingList;

use App\Planning\Application\View\ShoppingItemView;
use App\Planning\Application\View\ShoppingListView;
use App\Planning\Domain\Model\ShoppingItem;
use App\Planning\Domain\Repository\MealPlanRepository;
use App\Planning\Domain\Repository\ShoppingListRepository;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\View\QuantityView;
use App\Shared\Domain\Exception\NotFoundException;

final readonly class GetShoppingListHandler implements QueryHandler
{
    public function __construct(
        private MealPlanRepository $plans,
        private ShoppingListRepository $lists,
    ) {
    }

    /**
     * The plan is loaded even though the list could be found without it:
     * an unknown plan must be a 404 for the plan, and staleness needs the
     * plan anyway. Whether the list is stale is ShoppingList::isStaleFor()'s
     * answer; the handler does not compare timestamps itself, so the rule
     * stays in one place.
     *
     * A plan without a list is a 404 too, not an empty list: the resource
     * the client asked for has not been generated yet, and an empty list
     * would read as "nothing to buy".
     *
     * Items are sorted by position here rather than trusted to come back in
     * order: a collection loaded by the ORM has no order unless the mapping
     * says so, and this view promises one.
     *
     * @throws NotFoundException when the plan does not exist, or has no list yet
     */
    public function __invoke(GetShoppingList $query): ShoppingListView
    {
        $plan = $this->plans->get($query->planId);
        $list = $this->lists->findByPlan($plan->id()) ?? throw new NotFoundException('ShoppingList for MealPlan', $plan->id()->value);

        $items = $list->items();
        usort($items, static fn (ShoppingItem $a, ShoppingItem $b): int => $a->position() <=> $b->position());

        return new ShoppingListView(
            $list->id()->value,
            $list->mealPlanId()->value,
            $list->generatedAt(),
            $list->isStaleFor($plan),
            array_map(
                static fn (ShoppingItem $item): ShoppingItemView => new ShoppingItemView($item->productId()->value, $item->productName(), QuantityView::of($item->quantity()), $item->position()),
                $items,
            ),
        );
    }
}
