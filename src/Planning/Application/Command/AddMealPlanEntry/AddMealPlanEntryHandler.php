<?php

declare(strict_types=1);

namespace App\Planning\Application\Command\AddMealPlanEntry;

use App\Planning\Domain\Catalog\CatalogReader;
use App\Planning\Domain\Repository\MealPlanRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\TransactionalRunner;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use Psr\Clock\ClockInterface;

final readonly class AddMealPlanEntryHandler implements CommandHandler
{
    public function __construct(
        private MealPlanRepository $plans,
        private CatalogReader $catalog,
        private TransactionalRunner $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * PLN-07: the dish exists in the catalog. The aggregate cannot see the
     * catalog, so the handler asks the port before the plan is changed.
     * A missing dish is invalid input, not a 404: the plan the request is
     * addressed to exists, the dish is only a value inside the request (as
     * with ING-01 in Catalog). A missing plan is a 404 and comes from the
     * repository as it is.
     *
     * @throws NotFoundException   when the plan does not exist
     * @throws ValidationException PLN-02, PLN-03, PLN-07
     * @throws ConflictException   PLN-04
     */
    public function __invoke(AddMealPlanEntry $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $plan = $this->plans->get($command->planId);

            if ([] === $this->catalog->dishCompositions([$command->dishId])) {
                throw new ValidationException('PLN-07', \sprintf('Dish %s does not exist.', $command->dishId), 'dishId');
            }

            // No save call: the repository hands out the plan it tracks, and
            // the commit writes the change, as with any loaded aggregate.
            $plan->addEntry($command->newEntryId, $command->day, $command->dishId, $command->portions, $this->clock->now());
        });
    }
}
