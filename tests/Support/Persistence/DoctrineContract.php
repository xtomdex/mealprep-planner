<?php

declare(strict_types=1);

namespace App\Tests\Support\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;

/**
 * What the Doctrine subclasses of a contract share: the entity manager, a
 * real flush and clear, and a count of the SQL statements a call sends.
 * Each test runs in a transaction that DAMA rolls back.
 */
trait DoctrineContract
{
    protected function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function flushAndClear(): void
    {
        $this->entityManager()->flush();
        $this->entityManager()->clear();
    }

    /**
     * Counts with the DBAL debug middleware the bundle registers in the
     * debug-enabled test environment (the profiler's own data source), so no
     * test-only middleware is needed.
     *
     * @param callable(): mixed $call
     */
    protected function queriesOf(callable $call): int
    {
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        self::assertInstanceOf(DebugDataHolder::class, $holder);
        $holder->reset();

        $call();

        return \count($holder->getData()['default'] ?? []);
    }
}
