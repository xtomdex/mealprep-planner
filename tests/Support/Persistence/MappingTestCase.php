<?php

declare(strict_types=1);

namespace App\Tests\Support\Persistence;

use App\Shared\Infrastructure\Identity\SymfonyUuidGenerator;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base for tests that take an aggregate through a real PostgreSQL. Each test
 * runs inside a transaction that DAMA rolls back afterwards, so tests share
 * the schema but never each other's rows.
 *
 * A round trip always ends in flushAndClear(): the read that follows then
 * hydrates new objects from the rows, instead of handing back the instance
 * that was persisted from the identity map.
 */
abstract class MappingTestCase extends KernelTestCase
{
    protected function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function connection(): Connection
    {
        return $this->em()->getConnection();
    }

    protected function flushAndClear(): void
    {
        $this->em()->flush();
        $this->em()->clear();
    }

    protected static function nextId(): string
    {
        return (new SymfonyUuidGenerator())->nextId();
    }
}
