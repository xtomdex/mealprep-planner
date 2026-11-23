<?php

declare(strict_types=1);

namespace App\Planning\Application\Command\RemoveMealPlanEntry;

use App\Planning\Domain\Repository\MealPlanRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\TransactionalRunner;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\NotFoundException;
use Psr\Clock\ClockInterface;

final readonly class RemoveMealPlanEntryHandler implements CommandHandler
{
    public function __construct(
        private MealPlanRepository $plans,
        private TransactionalRunner $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * An unknown entry is the plan's call, not the handler's: the plan
     * ignores it while it is a draft and refuses every write once it is
     * finalized, so there is no lookup here that could disagree with it.
     *
     * @throws NotFoundException when the plan does not exist
     * @throws ConflictException PLN-04
     */
    public function __invoke(RemoveMealPlanEntry $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->plans->get($command->planId)->removeEntry($command->entryId, $this->clock->now());
        });
    }
}
