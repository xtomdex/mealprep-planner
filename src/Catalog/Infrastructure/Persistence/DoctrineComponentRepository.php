<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\ComponentIngredient;
use App\Catalog\Domain\Repository\ComponentRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Infrastructure\Persistence\QueryResult;
use App\Shared\Infrastructure\Persistence\Type\ComponentIdType;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * ComponentRepository over Doctrine; see DoctrineProductRepository for why
 * it wraps the EntityManager instead of extending a Doctrine repository.
 *
 * Lists of components are loaded with their lines in the same query (a
 * fetch join): every caller of findByIds() and all() goes on to read the
 * lines, and loading them lazily would cost one query per component. The
 * mapping's order-by keeps the lines in position order in the join too.
 * all() promises no order of the components.
 */
final readonly class DoctrineComponentRepository implements ComponentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function get(ComponentId $id): Component
    {
        return $this->entityManager->find(Component::class, $id) ?? throw new NotFoundException('Component', $id->value);
    }

    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $query = $this->withLines()
            ->where('c.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (ComponentId $id): string => $id->value, $ids), ArrayParameterType::STRING)
            ->getQuery();

        return QueryResult::listOf(Component::class, $query->getResult());
    }

    public function all(): array
    {
        return QueryResult::listOf(Component::class, $this->withLines()->getQuery()->getResult());
    }

    public function add(Component $component): void
    {
        $this->entityManager->persist($component);
    }

    /**
     * Asks whether one line exists, without loading a component: this runs
     * on every component creation that nests another one.
     */
    public function isReferencedAsChild(ComponentId $id): bool
    {
        $line = $this->entityManager->createQueryBuilder()
            ->select('l.position')
            ->from(ComponentIngredient::class, 'l')
            ->where('l.ref.componentId = :id')
            ->setParameter('id', $id, ComponentIdType::NAME)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $line;
    }

    private function withLines(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c', 'l')
            ->from(Component::class, 'c')
            ->leftJoin('c.ingredients', 'l');
    }
}
