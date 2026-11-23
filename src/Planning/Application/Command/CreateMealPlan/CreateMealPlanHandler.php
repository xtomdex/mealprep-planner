<?php

declare(strict_types=1);

namespace App\Planning\Application\Command\CreateMealPlan;

use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Repository\MealPlanRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\TransactionalRunner;
use App\Shared\Domain\Exception\ValidationException;
use Psr\Clock\ClockInterface;

final readonly class CreateMealPlanHandler implements CommandHandler
{
    public function __construct(
        private MealPlanRepository $plans,
        private TransactionalRunner $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * "Today" for PLN-01 is the date of now() in UTC, with no time-zone
     * setting. The users are in Spain, and UTC is never ahead of Spanish
     * time (one hour behind in winter, two in summer), so the UTC date is
     * never later than the Spanish one: the rule never rejects a plan that
     * starts on the user's today. The cost is leniency, not refusal: for an
     * hour or two after midnight in Spain, a plan for the day that has just
     * ended is still accepted. A per-user time zone would remove that gap,
     * but there are no users or settings in this demo to carry one.
     *
     * @throws ValidationException PLN-01
     */
    public function __invoke(CreateMealPlan $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $now = $this->clock->now();
            $today = $now->setTimezone(new \DateTimeZone('UTC'));

            $this->plans->add(MealPlan::create($command->id, $command->periodStart, $today, $now));
        });
    }
}
