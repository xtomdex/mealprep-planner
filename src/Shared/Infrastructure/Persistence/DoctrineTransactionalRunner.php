<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\TransactionalRunner;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * TransactionalRunner over Doctrine: the work and the flush run in one
 * database transaction, so the work's changes and the SQL they produce
 * commit together or not at all. Repositories only persist; this is the one
 * place that flushes.
 *
 * Doctrine closes the EntityManager after an exception inside a transaction,
 * because its unit of work may be half-applied. A closed manager refuses
 * every later call, so the next run() in the same process (the next test,
 * the next message in a worker) resets it here first. This is the right
 * place because every write passes through it; the registry resets the lazy
 * service in place, so repositories that captured the manager keep working.
 * Symfony's kernel.reset does the same between worker messages, which makes
 * this a second line of defence there, and the only one inside a single
 * process that never resets the kernel.
 */
final readonly class DoctrineTransactionalRunner implements TransactionalRunner
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
    }

    public function run(callable $work): mixed
    {
        $manager = $this->openManager();
        $connection = $manager->getConnection();

        $connection->beginTransaction();
        try {
            $result = $work();
            $manager->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $manager->close();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $e;
        }

        return $result;
    }

    private function openManager(): EntityManagerInterface
    {
        $manager = $this->doctrine->getManager();
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(\sprintf('The default manager must be an ORM EntityManager, got %s.', get_debug_type($manager)));
        }

        if (!$manager->isOpen()) {
            $this->doctrine->resetManager();

            return $this->openManager();
        }

        return $manager;
    }
}
