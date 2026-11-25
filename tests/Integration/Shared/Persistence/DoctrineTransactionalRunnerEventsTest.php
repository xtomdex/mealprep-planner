<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Persistence;

use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\Product;
use App\Planning\Domain\Event\MealPlanFinalized;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Shared\Infrastructure\Persistence\DoctrineTransactionalRunner;
use App\Tests\Support\Persistence\MappingTestCase;
use App\Tests\Support\Shared\Event\RecordingEventSubscriber;
use App\Tests\Support\Shared\Event\ThrowingEventSubscriber;
use App\Tests\Support\Shared\RecordingLogger;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LogLevel;

/**
 * The real event.bus with the test subscribers registered under when@test.
 */
final class DoctrineTransactionalRunnerEventsTest extends MappingTestCase
{
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    public function testEventsAreDispatchedAfterTheCommitAndNotDuringTheWork(): void
    {
        $plan = $this->planReadyToFinalize();
        $duringWork = null;

        $this->runner()->run(function () use ($plan, &$duringWork): void {
            $plan->finalize(new \DateTimeImmutable());
            $this->em()->persist($plan);
            $duringWork = \count($this->recorder()->received());
        });

        self::assertSame(0, $duringWork);
        self::assertCount(1, $this->recorder()->received());
        self::assertInstanceOf(MealPlanFinalized::class, $this->recorder()->received()[0]);
        self::assertSame([], $plan->releaseEvents(), 'Released once, so a second save cannot publish them again.');
    }

    /**
     * The plan is finalized, but the same unit of work also holds a product
     * whose name is taken, so the flush fails.
     */
    public function testARolledBackRunDispatchesNothingAndDropsTheRecordedEvents(): void
    {
        $plan = $this->planReadyToFinalize();
        $this->em()->persist(Product::create(new ProductId(self::nextId()), 'Taken', MeasurementCategory::Weight, new \DateTimeImmutable()));
        $this->em()->flush();

        try {
            $this->runner()->run(function () use ($plan): void {
                $plan->finalize(new \DateTimeImmutable());
                $this->em()->persist(Product::create(new ProductId(self::nextId()), 'Taken', MeasurementCategory::Weight, new \DateTimeImmutable()));
            });
            self::fail('The duplicate name should fail the flush.');
        } catch (UniqueConstraintViolationException) {
        }

        self::assertSame([], $this->recorder()->received());
        self::assertSame([], $plan->releaseEvents());
    }

    public function testWorkThatThrowsAfterRecordingAnEventDispatchesNothing(): void
    {
        $plan = $this->planReadyToFinalize();

        try {
            $this->runner()->run(static function () use ($plan): void {
                $plan->finalize(new \DateTimeImmutable());

                throw new \RuntimeException('Work failed after the plan was finalized.');
            });
        } catch (\RuntimeException) {
        }

        self::assertSame([], $this->recorder()->received());
        self::assertSame([], $plan->releaseEvents());
    }

    public function testAThrowingSubscriberIsLoggedAndTheOtherSubscribersAndEventsStillRun(): void
    {
        self::service(ThrowingEventSubscriber::class)->arm();
        $first = $this->planReadyToFinalize();
        $second = $this->planReadyToFinalize();

        $this->runner()->run(static function () use ($first, $second): void {
            $first->finalize(new \DateTimeImmutable());
            $second->finalize(new \DateTimeImmutable());
        });

        self::assertCount(2, $this->recorder()->received());
        $errors = array_values(array_filter($this->logger->records(), static fn (array $record): bool => LogLevel::ERROR === $record['level']));
        self::assertCount(2, $errors);
        self::assertSame(MealPlanFinalized::class, $errors[0]['context']['event']);
        self::assertInstanceOf(\Throwable::class, $errors[0]['context']['exception']);
    }

    private function runner(): DoctrineTransactionalRunner
    {
        return new DoctrineTransactionalRunner(self::getContainer()->get(ManagerRegistry::class), self::getContainer()->get('event.bus'), $this->logger);
    }

    private function recorder(): RecordingEventSubscriber
    {
        return self::service(RecordingEventSubscriber::class);
    }

    /**
     * A stored draft with one entry, still managed by the entity manager:
     * the runner finds it in the identity map, as it would find a plan a
     * handler loaded through its repository.
     */
    private function planReadyToFinalize(): MealPlan
    {
        $product = new ProductId(self::nextId());
        $this->em()->persist(Product::create($product, 'Rice '.$product->value, MeasurementCategory::Weight, new \DateTimeImmutable()));
        $dish = new DishId(self::nextId());
        $this->em()->flush();
        $this->em()->persist(Dish::create($dish, 'Bowl '.$dish->value, [new IngredientLine(IngredientRef::product($product), Quantity::of('100', Unit::Gram))], new \DateTimeImmutable()));
        $this->em()->flush();

        $plan = MealPlan::create(new MealPlanId(self::nextId()), new \DateTimeImmutable('2026-11-30'), new \DateTimeImmutable('2026-11-25'), new \DateTimeImmutable());
        $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-11-30'), $dish, 1, new \DateTimeImmutable());
        $this->em()->persist($plan);
        $this->em()->flush();

        return $plan;
    }
}
