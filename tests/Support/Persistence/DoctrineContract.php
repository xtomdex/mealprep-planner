<?php

declare(strict_types=1);

namespace App\Tests\Support\Persistence;

use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\Product;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Shared\Infrastructure\Identity\SymfonyUuidGenerator;
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

    protected function existingProduct(): ProductId
    {
        $id = new ProductId((new SymfonyUuidGenerator())->nextId());
        $this->entityManager()->persist(Product::create($id, 'Product '.$id->value, MeasurementCategory::Weight, new \DateTimeImmutable()));
        $this->entityManager()->flush();

        return $id;
    }

    protected function existingDish(): DishId
    {
        $id = new DishId((new SymfonyUuidGenerator())->nextId());
        $line = new IngredientLine(IngredientRef::product($this->existingProduct()), Quantity::of('100', Unit::Gram));
        $this->entityManager()->persist(Dish::create($id, 'Dish '.$id->value, [$line], new \DateTimeImmutable()));
        $this->entityManager()->flush();

        return $id;
    }

    protected function existingPlan(): MealPlanId
    {
        $id = new MealPlanId((new SymfonyUuidGenerator())->nextId());
        $this->entityManager()->persist(MealPlan::create($id, new \DateTimeImmutable('2026-11-30'), new \DateTimeImmutable('2026-11-25'), new \DateTimeImmutable()));
        $this->entityManager()->flush();

        return $id;
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
