<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

use App\Shared\Domain\Exception\ValidationException;

/**
 * What will be eaten during seven days in a row, starting on any date.
 *
 * Days are calendar dates, not moments. Every incoming \DateTimeImmutable is
 * cut to midnight at this boundary and days are compared as Y-m-d strings:
 * "Monday 18:30" and "Monday 09:00" are the same day, and comparing objects
 * would make them different, or make the same date in two time zones
 * different instants. A Date value object would say this in the type, but
 * one class with one helper is enough for the two places that need it.
 *
 * The domain has no clock: "now" and "today" are passed in, so tests and
 * handlers decide what time it is.
 */
final class MealPlan
{
    public const int DAYS = 7;

    private MealPlanStatus $status;

    private \DateTimeImmutable $updatedAt;

    private readonly \DateTimeImmutable $periodEnd;

    private function __construct(
        private readonly MealPlanId $id,
        private readonly \DateTimeImmutable $periodStart,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->periodEnd = $periodStart->modify(\sprintf('+%d days', self::DAYS - 1));
        $this->status = MealPlanStatus::Draft;
        $this->updatedAt = $createdAt;
    }

    /**
     * PLN-01: the period does not start before today. Planning the past is
     * always a mistake; "today" comes from the caller, like "now", so the
     * rule does not depend on the server clock or its time zone.
     *
     * @throws ValidationException PLN-01
     */
    public static function create(MealPlanId $id, \DateTimeImmutable $periodStart, \DateTimeImmutable $today, \DateTimeImmutable $now): self
    {
        $periodStart = self::toDay($periodStart);

        if (self::dayKey($periodStart) < self::dayKey($today)) {
            throw new ValidationException('PLN-01', \sprintf('A plan cannot start in the past: %s is before %s.', self::dayKey($periodStart), self::dayKey($today)), 'periodStart');
        }

        return new self($id, $periodStart, $now);
    }

    public function id(): MealPlanId
    {
        return $this->id;
    }

    public function periodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    /**
     * Inclusive: the seventh day of the plan.
     */
    public function periodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function status(): MealPlanStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function toDay(\DateTimeImmutable $moment): \DateTimeImmutable
    {
        return $moment->setTime(0, 0);
    }

    // ISO dates sort as strings, so "<" on keys is "earlier day".
    private static function dayKey(\DateTimeImmutable $day): string
    {
        return $day->format('Y-m-d');
    }
}
