<?php

declare(strict_types=1);

namespace App\Tests\Support\Persistence;

use App\Shared\Domain\Identity\Uuid;
use App\Shared\Infrastructure\Identity\SymfonyUuidGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base for the contract tests in tests/Contract. A contract is an abstract
 * class of behaviour; each implementation gets a concrete subclass.
 *
 * It extends KernelTestCase so the Doctrine subclasses can use the
 * container; the in-memory ones never boot a kernel. flushAndClear() sits
 * between arrange and assert: a no-op for a fake, a real round trip to the
 * database for Doctrine, so a contract test never reads back the object it
 * just handed over.
 */
abstract class RepositoryContract extends KernelTestCase
{
    protected function flushAndClear(): void
    {
    }

    protected static function nextId(): string
    {
        return (new SymfonyUuidGenerator())->nextId();
    }

    /**
     * Ids as sorted strings, for "the same ones, in any order".
     *
     * @param list<Uuid> $ids
     *
     * @return list<string>
     */
    protected static function sorted(array $ids): array
    {
        $values = array_map(static fn (Uuid $id): string => $id->value, $ids);
        sort($values);

        return $values;
    }
}
