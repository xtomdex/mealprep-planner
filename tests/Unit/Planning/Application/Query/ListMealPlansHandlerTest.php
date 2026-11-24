<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Application\Query;

use App\Planning\Application\Query\ListMealPlans\ListMealPlans;
use App\Planning\Application\Query\ListMealPlans\ListMealPlansHandler;
use App\Planning\Application\View\MealPlanSummaryView;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Domain\Identity\DishId;
use App\Tests\Support\Planning\InMemoryMealPlanRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ListMealPlansHandlerTest extends TestCase
{
    private const string OLD = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string NEW_A = '0193a5e0-7b1c-7a3e-9f00-000000000002';
    private const string NEW_B = '0193a5e0-7b1c-7a3e-9f00-000000000003';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string BOWL = '0193a5e0-7b1c-7a3e-9f00-0000000000d2';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';
    private const string E2 = '0193a5e0-7b1c-7a3e-9f00-0000000000e2';
    private const string E3 = '0193a5e0-7b1c-7a3e-9f00-0000000000e3';

    private InMemoryMealPlanRepository $plans;

    private MockClock $clock;

    private ListMealPlansHandler $handler;

    protected function setUp(): void
    {
        $this->plans = new InMemoryMealPlanRepository();
        $this->clock = new MockClock('2026-11-24 10:00:00');
        $this->handler = new ListMealPlansHandler($this->plans);
    }

    public function testTheNewestPeriodComesFirstAndPlansStartingTheSameDayAreOrderedById(): void
    {
        $this->add(self::NEW_B, '2026-12-07');
        $this->add(self::OLD, '2026-11-30');
        $this->add(self::NEW_A, '2026-12-07');

        $ids = array_map(static fn (MealPlanSummaryView $v): string => $v->id, ($this->handler)(new ListMealPlans()));

        self::assertSame([self::NEW_A, self::NEW_B, self::OLD], $ids);
    }

    public function testASummaryCarriesThePeriodTheStatusAndTheNumberOfEntries(): void
    {
        $plan = $this->add(self::OLD, '2026-11-30');
        $plan->addEntry(new MealPlanEntryId(self::E1), new \DateTimeImmutable('2026-11-30'), new DishId(self::SOUP), 2, $this->clock->now());
        $plan->addEntry(new MealPlanEntryId(self::E2), new \DateTimeImmutable('2026-12-01'), new DishId(self::BOWL), 1, $this->clock->now());
        // Same day and dish as E1: merged into it, so still two entries (PLN-06).
        $plan->addEntry(new MealPlanEntryId(self::E3), new \DateTimeImmutable('2026-11-30'), new DishId(self::SOUP), 1, $this->clock->now());
        $plan->finalize($this->clock->now());

        self::assertEquals(
            [new MealPlanSummaryView(self::OLD, '2026-11-30', '2026-12-06', 'finalized', 2)],
            ($this->handler)(new ListMealPlans()),
        );
    }

    public function testNoPlansGiveAnEmptyList(): void
    {
        self::assertSame([], ($this->handler)(new ListMealPlans()));
    }

    private function add(string $id, string $periodStart): MealPlan
    {
        $plan = MealPlan::create(new MealPlanId($id), new \DateTimeImmutable($periodStart), $this->clock->now(), $this->clock->now());
        $this->plans->add($plan);

        return $plan;
    }
}
