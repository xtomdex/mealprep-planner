<?php

declare(strict_types=1);

namespace App\Planning\Infrastructure\Persistence;

use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ShoppingList;
use App\Planning\Domain\Repository\ShoppingListRepository;
use App\Planning\Infrastructure\Persistence\Type\MealPlanIdType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ShoppingListRepository over Doctrine; see DoctrineProductRepository for
 * why it wraps the EntityManager.
 *
 * add() does not check SHL-01 (one list per plan): the UNIQUE index on
 * meal_plan_id refuses a second list at the flush. A regenerated list is
 * the same managed object with new items, so it needs no add() at all.
 */
final readonly class DoctrineShoppingListRepository implements ShoppingListRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * The items come in the same query: every caller reads them.
     */
    public function findByPlan(MealPlanId $mealPlanId): ?ShoppingList
    {
        $list = $this->entityManager->createQueryBuilder()
            ->select('l', 'i')
            ->from(ShoppingList::class, 'l')
            ->leftJoin('l.items', 'i')
            ->where('l.mealPlanId = :plan')
            ->setParameter('plan', $mealPlanId, MealPlanIdType::NAME)
            ->getQuery()
            ->getOneOrNullResult();

        if (null !== $list && !$list instanceof ShoppingList) {
            throw new \LogicException(\sprintf('Expected a ShoppingList, got %s.', get_debug_type($list)));
        }

        return $list;
    }

    public function add(ShoppingList $list): void
    {
        $this->entityManager->persist($list);
    }
}
