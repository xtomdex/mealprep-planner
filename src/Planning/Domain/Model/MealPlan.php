<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

use App\Planning\Domain\Event\MealPlanFinalized;
use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\DishId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

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
 *
 * updatedAt tracks the entries only; finalize() sets finalizedAt and leaves
 * updatedAt alone. That keeps staleness of a shopping list one comparison
 * (plan.updatedAt > list.generatedAt), with no case for finalized plans.
 */
final class MealPlan
{
    use AggregateRoot;

    public const int DAYS = 7;

    private MealPlanStatus $status;

    /** @var Collection<int, MealPlanEntry> in insertion order */
    private Collection $entries;

    private \DateTimeImmutable $updatedAt;

    private ?\DateTimeImmutable $finalizedAt = null;

    private readonly \DateTimeImmutable $periodEnd;

    private function __construct(
        private readonly MealPlanId $id,
        private readonly \DateTimeImmutable $periodStart,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->periodEnd = $periodStart->modify(\sprintf('+%d days', self::DAYS - 1));
        $this->status = MealPlanStatus::Draft;
        $this->entries = new ArrayCollection();
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

    /**
     * PLN-04: the plan is still a draft.
     * PLN-02: the day lies within the plan's period.
     * PLN-03: at least one portion.
     * PLN-06: a plan has at most one entry per (day, dish). Adding a pair
     * that is already there adds the portions to that entry and returns its
     * id; $newId is then left unused. Merging instead of rejecting matches
     * what the user means by "add soup on Monday" twice, and makes the rule
     * hold by construction rather than by a check a caller could skip.
     *
     * @throws ConflictException   PLN-04
     * @throws ValidationException PLN-02, PLN-03
     */
    public function addEntry(MealPlanEntryId $newId, \DateTimeImmutable $day, DishId $dishId, int $portions, \DateTimeImmutable $now): MealPlanEntryId
    {
        $this->assertEditable();

        $day = self::toDay($day);
        $key = self::dayKey($day);

        if ($key < self::dayKey($this->periodStart) || $key > self::dayKey($this->periodEnd)) {
            throw new ValidationException('PLN-02', \sprintf('Day %s is outside the plan period %s..%s.', $key, self::dayKey($this->periodStart), self::dayKey($this->periodEnd)), 'day');
        }

        if ($portions < 1) {
            throw new ValidationException('PLN-03', \sprintf('An entry needs at least one portion, got %d.', $portions), 'portions');
        }

        $this->updatedAt = $now;

        foreach ($this->entries as $entry) {
            if (self::dayKey($entry->day()) === $key && $entry->dishId()->equals($dishId)) {
                $entry->addPortions($portions);

                return $entry->id();
            }
        }

        $this->entries->add(new MealPlanEntry($this, $newId, $day, $dishId, $portions));

        return $newId;
    }

    /**
     * Idempotent, like an HTTP DELETE: removing an entry that is not there
     * (any more) leaves the plan as it is, and updatedAt moves only when an
     * entry was actually removed, so a repeated request does not make the
     * shopping list look stale.
     *
     * PLN-04 still applies to an unknown id: a finalized plan refuses every
     * write, so the answer does not depend on what the caller guessed.
     *
     * @throws ConflictException PLN-04
     */
    public function removeEntry(MealPlanEntryId $entryId, \DateTimeImmutable $now): void
    {
        $this->assertEditable();

        foreach ($this->entries as $entry) {
            if ($entry->id()->equals($entryId)) {
                $this->entries->removeElement($entry);
                $this->updatedAt = $now;

                return;
            }
        }
    }

    /**
     * Freezes the plan and records MealPlanFinalized.
     *
     * PLN-04: only a draft can be finalized, so finalizing twice is refused
     * and the event is recorded once per plan.
     * PLN-05: the plan has at least one entry; an empty plan has nothing to
     * cook or buy.
     *
     * Both are conflicts, not invalid input: the request is fine, the plan's
     * current state is not, and adding an entry makes PLN-05 go away.
     *
     * @throws ConflictException PLN-04, PLN-05
     */
    public function finalize(\DateTimeImmutable $now): void
    {
        $this->assertEditable();

        if ($this->entries->isEmpty()) {
            throw new ConflictException('PLN-05', \sprintf('Meal plan %s has no entries and cannot be finalized.', $this->id));
        }

        $this->status = MealPlanStatus::Finalized;
        $this->finalizedAt = $now;
        $this->recordEvent(new MealPlanFinalized($this->id, $now));
    }

    /**
     * Ordered by day, then by insertion. A plain list, not the Collection:
     * callers cannot add or remove entries behind the aggregate's back.
     *
     * @return list<MealPlanEntry>
     */
    public function entries(): array
    {
        $entries = array_values($this->entries->toArray());
        // usort is stable since PHP 8.0, so entries of one day keep their insertion order.
        usort($entries, static fn (MealPlanEntry $a, MealPlanEntry $b): int => self::dayKey($a->day()) <=> self::dayKey($b->day()));

        return $entries;
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

    public function finalizedAt(): ?\DateTimeImmutable
    {
        return $this->finalizedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * PLN-04: a finalized plan never changes. Every write path calls this
     * first, so a new method cannot forget the rule without it showing up
     * in review as a missing first line.
     *
     * @throws ConflictException PLN-04
     */
    private function assertEditable(): void
    {
        if (MealPlanStatus::Finalized === $this->status) {
            throw new ConflictException('PLN-04', \sprintf('Meal plan %s is finalized and cannot be changed.', $this->id));
        }
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
