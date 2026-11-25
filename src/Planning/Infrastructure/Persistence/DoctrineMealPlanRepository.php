<?php

declare(strict_types=1);

namespace App\Planning\Infrastructure\Persistence;

use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Repository\MealPlanRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Infrastructure\Persistence\QueryResult;
use Doctrine\ORM\EntityManagerInterface;

/**
 * MealPlanRepository over Doctrine. A plain class around the EntityManager,
 * not a ServiceEntityRepository, so the port is its whole public surface
 * (DoctrineProductRepository says more).
 *
 * There is no save(): a plan obtained from get() is managed, and the runner's
 * flush stores whatever its methods changed. all() fetch-joins the entries,
 * which come in the mapping's order (day, then id); it promises no order of
 * the plans.
 */
final readonly class DoctrineMealPlanRepository implements MealPlanRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function get(MealPlanId $id): MealPlan
    {
        return $this->entityManager->find(MealPlan::class, $id) ?? throw new NotFoundException('MealPlan', $id->value);
    }

    public function all(): array
    {
        // A left join: a new plan has no entries yet and must still be listed.
        $query = $this->entityManager->createQueryBuilder()
            ->select('p', 'e')
            ->from(MealPlan::class, 'p')
            ->leftJoin('p.entries', 'e')
            ->getQuery();

        return QueryResult::listOf(MealPlan::class, $query->getResult());
    }

    public function add(MealPlan $plan): void
    {
        $this->entityManager->persist($plan);
    }
}
