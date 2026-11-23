<?php

declare(strict_types=1);

namespace App\Planning\Application\Command\FinalizeMealPlan;

use App\Planning\Domain\Repository\MealPlanRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\TransactionalRunner;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\NotFoundException;
use Psr\Clock\ClockInterface;

final readonly class FinalizeMealPlanHandler implements CommandHandler
{
    public function __construct(
        private MealPlanRepository $plans,
        private TransactionalRunner $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The plan records MealPlanFinalized, and the event stays recorded:
     * nothing listens to it yet, so there is no dispatcher or outbox to hand
     * it to. Publishing belongs after the commit, when the first listener
     * arrives, not in this handler.
     *
     * @throws NotFoundException when the plan does not exist
     * @throws ConflictException PLN-04, PLN-05
     */
    public function __invoke(FinalizeMealPlan $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->plans->get($command->planId)->finalize($this->clock->now());
        });
    }
}
