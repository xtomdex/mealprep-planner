<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Persistence;

use App\Catalog\Domain\Model\Product;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Infrastructure\Persistence\DoctrineTransactionalRunner;
use App\Tests\Support\Persistence\MappingTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\NullLogger;

final class DoctrineTransactionalRunnerTest extends MappingTestCase
{
    public function testTheWorkIsFlushedAndCommittedAndItsResultReturned(): void
    {
        $id = new ProductId(self::nextId());

        $result = $this->runner()->run(function () use ($id): string {
            $this->em()->persist($this->product($id, 'Rice'));

            return 'done';
        });
        $this->em()->clear();

        self::assertSame('done', $result);
        self::assertNotNull($this->em()->find(Product::class, $id));
    }

    public function testAnExceptionInTheWorkRollsBackAndIsRethrownUnchanged(): void
    {
        $id = new ProductId(self::nextId());
        $failure = new \RuntimeException('Work failed.');

        $caught = null;
        try {
            $this->runner()->run(function () use ($id, $failure): void {
                $this->em()->persist($this->product($id, 'Rice'));
                $this->em()->flush();

                throw $failure;
            });
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        self::assertSame($failure, $caught);

        self::assertFalse($this->connection()->fetchOne('SELECT 1 FROM catalog_products WHERE id = ?', [$id->value]));
    }

    /**
     * Doctrine closes the manager after a failed transaction. The next run
     * resets it, and the manager reference taken before the failure (as a
     * repository holds it) is usable again: the registry resets the lazy
     * service in place instead of replacing it.
     */
    public function testAFailedRunClosesTheManagerAndTheNextRunReopensIt(): void
    {
        $manager = $this->em();
        $this->runner()->run(fn () => $manager->persist($this->product(new ProductId(self::nextId()), 'Rice')));

        try {
            $this->runner()->run(fn () => $manager->persist($this->product(new ProductId(self::nextId()), 'Rice')));
            self::fail('A duplicate name should fail the flush.');
        } catch (UniqueConstraintViolationException) {
        }
        self::assertFalse($manager->isOpen());

        $id = new ProductId(self::nextId());
        $this->runner()->run(fn () => $manager->persist($this->product($id, 'Basmati rice')));

        self::assertTrue($manager->isOpen());
        self::assertSame('Basmati rice', $this->connection()->fetchOne('SELECT name FROM catalog_products WHERE id = ?', [$id->value]));
    }

    private function runner(): DoctrineTransactionalRunner
    {
        return new DoctrineTransactionalRunner(
            self::getContainer()->get(ManagerRegistry::class),
            self::getContainer()->get('event.bus'),
            new NullLogger(),
        );
    }

    private function product(ProductId $id, string $name): Product
    {
        return Product::create($id, $name, MeasurementCategory::Weight, new \DateTimeImmutable());
    }
}
