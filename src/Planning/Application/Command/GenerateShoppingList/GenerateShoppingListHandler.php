<?php

declare(strict_types=1);

namespace App\Planning\Application\Command\GenerateShoppingList;

use App\Planning\Domain\Exception\CatalogIntegrityViolation;
use App\Planning\Domain\Model\ShoppingList;
use App\Planning\Domain\Repository\MealPlanRepository;
use App\Planning\Domain\Repository\ShoppingListRepository;
use App\Planning\Domain\Service\MealPlanDecomposer;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\TransactionalRunner;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use Psr\Clock\ClockInterface;

final readonly class GenerateShoppingListHandler implements CommandHandler
{
    public function __construct(
        private MealPlanRepository $plans,
        private ShoppingListRepository $lists,
        private MealPlanDecomposer $decomposer,
        private TransactionalRunner $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Read, decompose and write in one transaction, so the list matches the
     * plan as it was read. Drafts and finalized plans alike: a draft's list
     * is a preview, and staleness is visible through ShoppingList::isStaleFor().
     *
     * Only the products are kept; the component portions the decomposer
     * also returns are a prep list, not something to buy. The amounts go in
     * unrounded: the list rounds every item up to a whole canonical amount
     * itself, so no caller can store a fraction of a gram.
     *
     * An empty plan decomposes to nothing and the list refuses it (SHL-02),
     * before anything is stored or replaced.
     *
     * @throws NotFoundException         when the plan does not exist
     * @throws ValidationException       SHL-02
     * @throws CatalogIntegrityViolation when the catalog cannot answer for the plan's dishes
     */
    public function __invoke(GenerateShoppingList $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $plan = $this->plans->get($command->planId);
            $products = $this->decomposer->decompose($plan)->products;
            $now = $this->clock->now();

            $list = $this->lists->findByPlan($plan->id());
            if (null === $list) {
                $this->lists->add(ShoppingList::generate($command->newListId, $plan->id(), $products, $now));

                return;
            }

            $list->replaceItems($products, $now);
        });
    }
}
