<?php

declare(strict_types=1);

namespace App\Tests\Contract\Planning;

use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntry;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\MealPlanStatus;
use App\Planning\Domain\Repository\MealPlanRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Support\Persistence\RepositoryContract;

abstract class MealPlanRepositoryContract extends RepositoryContract
{
    abstract protected function repository(): MealPlanRepository;

    public function testAnAddedPlanIsReturnedByGetWithItsEntriesByDay(): void
    {
        [$soup, $salad] = [$this->existingDish(), $this->existingDish()];
        $plan = $this->newPlan();
        $late = $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-12-02'), $soup, 2, new \DateTimeImmutable());
        $early = $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-11-30'), $salad, 1, new \DateTimeImmutable());
        $this->repository()->add($plan);
        $this->flushAndClear();

        $loaded = $this->repository()->get($plan->id());

        self::assertSame(MealPlanStatus::Draft, $loaded->status());
        self::assertSame('2026-11-30', $loaded->periodStart()->format('Y-m-d'));
        self::assertSame([(string) $early, (string) $late], array_map(static fn (MealPlanEntry $e): string => (string) $e->id(), $loaded->entries()));
    }

    public function testGetOfAnUnknownIdThrowsNotFound(): void
    {
        $id = self::nextId();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage(\sprintf('MealPlan "%s" was not found.', $id));

        $this->repository()->get(new MealPlanId($id));
    }

    /**
     * There is no save(): what a caller changes on a plan it got is kept.
     */
    public function testAChangeToAPlanFromGetIsKeptWithoutAnotherAdd(): void
    {
        $plan = $this->newPlan();
        $plan->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-11-30'), $this->existingDish(), 1, new \DateTimeImmutable());
        $this->repository()->add($plan);
        $this->flushAndClear();

        $this->repository()->get($plan->id())->finalize(new \DateTimeImmutable());
        $this->flushAndClear();

        self::assertSame(MealPlanStatus::Finalized, $this->repository()->get($plan->id())->status());
    }

    public function testAllReturnsEveryPlanIncludingOneWithoutEntries(): void
    {
        $withEntry = $this->newPlan();
        $withEntry->addEntry(new MealPlanEntryId(self::nextId()), new \DateTimeImmutable('2026-11-30'), $this->existingDish(), 1, new \DateTimeImmutable());
        $empty = $this->newPlan();
        $this->repository()->add($withEntry);
        $this->repository()->add($empty);
        $this->flushAndClear();

        self::assertSame(
            self::sorted([$withEntry->id(), $empty->id()]),
            self::sorted(array_map(static fn (MealPlan $p): MealPlanId => $p->id(), $this->repository()->all())),
        );
    }

    private function newPlan(): MealPlan
    {
        return MealPlan::create(new MealPlanId(self::nextId()), new \DateTimeImmutable('2026-11-30'), new \DateTimeImmutable('2026-11-25'), new \DateTimeImmutable());
    }
}
