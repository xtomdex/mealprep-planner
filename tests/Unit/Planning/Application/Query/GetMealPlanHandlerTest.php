<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Application\Query;

use App\Planning\Application\Query\GetMealPlan\GetMealPlan;
use App\Planning\Application\Query\GetMealPlan\GetMealPlanHandler;
use App\Planning\Application\View\MealPlanEntryView;
use App\Planning\Application\View\MealPlanView;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\DishId;
use App\Tests\Support\Planning\InMemoryMealPlanRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class GetMealPlanHandlerTest extends TestCase
{
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string UNKNOWN_PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000009';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string BOWL = '0193a5e0-7b1c-7a3e-9f00-0000000000d2';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';
    private const string E2 = '0193a5e0-7b1c-7a3e-9f00-0000000000e2';
    private const string E3 = '0193a5e0-7b1c-7a3e-9f00-0000000000e3';

    private MealPlan $plan;

    private MockClock $clock;

    private GetMealPlanHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-24 10:00:00');
        $this->plan = MealPlan::create(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-30'), $this->clock->now(), $this->clock->now());
        $plans = new InMemoryMealPlanRepository();
        $plans->add($this->plan);

        $this->handler = new GetMealPlanHandler($plans);
    }

    public function testEntriesComeOrderedByDayThenByInsertion(): void
    {
        $this->add(self::E1, '2026-12-02', self::SOUP, 1);
        $this->add(self::E2, '2026-11-30', self::BOWL, 2);
        $this->add(self::E3, '2026-12-02', self::BOWL, 3);

        $view = $this->get();

        self::assertEquals([
            new MealPlanEntryView(self::E2, '2026-11-30', self::BOWL, 2),
            new MealPlanEntryView(self::E1, '2026-12-02', self::SOUP, 1),
            new MealPlanEntryView(self::E3, '2026-12-02', self::BOWL, 3),
        ], $view->entries);
    }

    public function testADraftHasNoFinalizedAt(): void
    {
        $view = $this->get();

        self::assertSame(self::PLAN, $view->id);
        self::assertSame('2026-11-30', $view->periodStart);
        self::assertSame('2026-12-06', $view->periodEnd);
        self::assertSame('draft', $view->status);
        self::assertNull($view->finalizedAt);
        self::assertSame([], $view->entries);
    }

    public function testAFinalizedPlanShowsWhenItWasFinalized(): void
    {
        $this->add(self::E1, '2026-12-02', self::SOUP, 1);
        $this->clock->modify('+2 hours');
        $this->plan->finalize($this->clock->now());

        $view = $this->get();

        self::assertSame('finalized', $view->status);
        self::assertEquals(new \DateTimeImmutable('2026-11-24 12:00:00'), $view->finalizedAt);
    }

    public function testAnUnknownPlanIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        ($this->handler)(new GetMealPlan(new MealPlanId(self::UNKNOWN_PLAN)));
    }

    private function add(string $entryId, string $day, string $dishId, int $portions): void
    {
        $this->plan->addEntry(new MealPlanEntryId($entryId), new \DateTimeImmutable($day), new DishId($dishId), $portions, $this->clock->now());
    }

    private function get(): MealPlanView
    {
        return ($this->handler)(new GetMealPlan(new MealPlanId(self::PLAN)));
    }
}
