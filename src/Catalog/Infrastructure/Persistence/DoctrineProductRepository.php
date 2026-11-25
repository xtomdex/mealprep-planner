<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\ProductId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ProductRepository over Doctrine.
 *
 * A plain class around the EntityManager, not a ServiceEntityRepository:
 * inheriting that would publish find(), findBy() and createQueryBuilder()
 * on the service, and any caller could bypass the port with them. The port
 * is the whole public surface. The same holds for every Doctrine repository
 * in this codebase.
 *
 * add() only persists; DoctrineTransactionalRunner flushes, so a repository
 * never decides when a transaction ends. all() promises no order: callers
 * that show a list sort it themselves.
 */
final readonly class DoctrineProductRepository implements ProductRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function get(ProductId $id): Product
    {
        return $this->entityManager->find(Product::class, $id) ?? throw new NotFoundException('Product', $id->value);
    }

    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->entityManager->getRepository(Product::class)->findBy(['id' => $ids]);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(Product::class)->findAll();
    }

    public function add(Product $product): void
    {
        $this->entityManager->persist($product);
    }
}
