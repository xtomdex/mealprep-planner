<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Repository\DishRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Infrastructure\Persistence\QueryResult;
use Doctrine\ORM\EntityManagerInterface;

/**
 * DishRepository over Doctrine; see DoctrineProductRepository for why it
 * wraps the EntityManager, and DoctrineComponentRepository for why lists
 * fetch-join their lines. all() promises no order of the dishes.
 */
final readonly class DoctrineDishRepository implements DishRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function get(DishId $id): Dish
    {
        return $this->entityManager->find(Dish::class, $id) ?? throw new NotFoundException('Dish', $id->value);
    }

    public function all(): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('d', 'l')
            ->from(Dish::class, 'd')
            ->leftJoin('d.ingredients', 'l')
            ->getQuery();

        return QueryResult::listOf(Dish::class, $query->getResult());
    }

    public function add(Dish $dish): void
    {
        $this->entityManager->persist($dish);
    }
}
