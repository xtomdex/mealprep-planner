<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Domain\Model;

use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\MealPlanStatus;
use App\Shared\Domain\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class MealPlanTest extends TestCase
{
    private const string ID = '0193a5e0-7b1c-7a3e-9f00-000000000001';

    public function testCreateStartsADraftOfSevenDaysAtMidnight(): void
    {
        $now = new \DateTimeImmutable('2026-11-12 10:00:00');

        $plan = MealPlan::create(new MealPlanId(self::ID), new \DateTimeImmutable('2026-11-16 18:30:00'), $now, $now);

        self::assertSame('2026-11-16 00:00:00', $plan->periodStart()->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-22 00:00:00', $plan->periodEnd()->format('Y-m-d H:i:s'));
        self::assertSame(MealPlanStatus::Draft, $plan->status());
        self::assertSame($now, $plan->createdAt());
        self::assertSame($now, $plan->updatedAt());
        self::assertTrue((new MealPlanId(self::ID))->equals($plan->id()));
    }

    public function testPln01AllowsAPlanStartingTodayWhateverTheTimeOfDay(): void
    {
        $today = new \DateTimeImmutable('2026-11-12 23:59:00');

        $plan = MealPlan::create(new MealPlanId(self::ID), new \DateTimeImmutable('2026-11-12 00:00:00'), $today, $today);

        self::assertSame('2026-11-12', $plan->periodStart()->format('Y-m-d'));
    }

    public function testPln01RejectsAPlanStartingYesterday(): void
    {
        try {
            MealPlan::create(new MealPlanId(self::ID), new \DateTimeImmutable('2026-11-11 23:00:00'), new \DateTimeImmutable('2026-11-12 00:30:00'), new \DateTimeImmutable());
            self::fail('Expected PLN-01.');
        } catch (ValidationException $e) {
            self::assertSame('PLN-01', $e->errorCode());
            self::assertSame('periodStart', $e->field());
        }
    }
}
