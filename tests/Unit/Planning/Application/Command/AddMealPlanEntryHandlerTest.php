<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Application\Command;

use App\Planning\Application\Command\AddMealPlanEntry\AddMealPlanEntry;
use App\Planning\Application\Command\AddMealPlanEntry\AddMealPlanEntryHandler;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Planning\InMemoryCatalogReader;
use App\Tests\Support\Planning\InMemoryMealPlanRepository;
use App\Tests\Support\Shared\ImmediateTransactionalRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AddMealPlanEntryHandlerTest extends TestCase
{
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string OTHER_PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000002';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string UNKNOWN_DISH = '0193a5e0-7b1c-7a3e-9f00-0000000000d9';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string E1 = '0193a5e0-7b1c-7a3e-9f00-0000000000e1';
    private const string E2 = '0193a5e0-7b1c-7a3e-9f00-0000000000e2';

    private InMemoryMealPlanRepository $plans;

    private ImmediateTransactionalRunner $transaction;

    private MockClock $clock;

    private AddMealPlanEntryHandler $handler;

    protected function setUp(): void
    {
        $catalog = (new InMemoryCatalogReader())
            ->withProduct(self::RICE, 'Rice')
            ->withDish(self::SOUP, [self::RICE => Quantity::of('80', Unit::Gram)]);

        // Monday 16 .. Sunday 22 November 2026, created the week before.
        $this->plans = new InMemoryMealPlanRepository();
        $this->plans->add(MealPlan::create(new MealPlanId(self::PLAN), new \DateTimeImmutable('2026-11-16'), new \DateTimeImmutable('2026-11-12'), new \DateTimeImmutable('2026-11-12 10:00')));

        $this->transaction = new ImmediateTransactionalRunner();
        $this->clock = new MockClock('2026-11-13 09:00:00');
        $this->handler = new AddMealPlanEntryHandler($this->plans, $catalog, $this->transaction, $this->clock);
    }

    public function testANewEntryIsStoredUnderTheGivenId(): void
    {
        ($this->handler)($this->add(self::E1, '2026-11-17', 2));

        [$entry] = $this->plan()->entries();
        self::assertTrue((new MealPlanEntryId(self::E1))->equals($entry->id()));
        self::assertSame('2026-11-17', $entry->day()->format('Y-m-d'));
        self::assertTrue((new DishId(self::SOUP))->equals($entry->dishId()));
        self::assertSame(2, $entry->portions());
        self::assertEquals($this->clock->now(), $this->plan()->updatedAt());
        self::assertSame(1, $this->transaction->runs());
    }

    public function testPln06TheSameDayAndDishAgainAddsPortionsToTheExistingEntryAndKeepsItsId(): void
    {
        ($this->handler)($this->add(self::E1, '2026-11-17', 2));
        ($this->handler)($this->add(self::E2, '2026-11-17 19:30', 1));

        $entries = $this->plan()->entries();
        self::assertCount(1, $entries);
        self::assertTrue((new MealPlanEntryId(self::E1))->equals($entries[0]->id()), 'the second id is left unused');
        self::assertSame(3, $entries[0]->portions());
    }

    public function testPln07AnUnknownDishIsRejectedOnTheDishIdFieldAndThePlanIsUnchanged(): void
    {
        $updatedAt = $this->plan()->updatedAt();

        try {
            ($this->handler)(new AddMealPlanEntry(new MealPlanId(self::PLAN), new MealPlanEntryId(self::E1), new \DateTimeImmutable('2026-11-17'), new DishId(self::UNKNOWN_DISH), 1));
            self::fail('Expected PLN-07.');
        } catch (ValidationException $e) {
            self::assertSame('PLN-07', $e->errorCode());
            self::assertSame('dishId', $e->field());
        }

        self::assertSame([], $this->plan()->entries());
        self::assertSame($updatedAt, $this->plan()->updatedAt());
    }

    public function testAnUnknownPlanSurfacesTheRepositorysNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);

        ($this->handler)(new AddMealPlanEntry(new MealPlanId(self::OTHER_PLAN), new MealPlanEntryId(self::E1), new \DateTimeImmutable('2026-11-17'), new DishId(self::SOUP), 1));
    }

    public function testPln04AFinalizedPlanRefusesTheEntry(): void
    {
        ($this->handler)($this->add(self::E1, '2026-11-17', 1));
        $this->plan()->finalize(new \DateTimeImmutable('2026-11-13 10:00'));

        try {
            ($this->handler)($this->add(self::E2, '2026-11-18', 1));
            self::fail('Expected PLN-04.');
        } catch (ConflictException $e) {
            self::assertSame('PLN-04', $e->errorCode());
        }

        self::assertCount(1, $this->plan()->entries());
    }

    private function add(string $entryId, string $day, int $portions): AddMealPlanEntry
    {
        return new AddMealPlanEntry(new MealPlanId(self::PLAN), new MealPlanEntryId($entryId), new \DateTimeImmutable($day), new DishId(self::SOUP), $portions);
    }

    private function plan(): MealPlan
    {
        return $this->plans->get(new MealPlanId(self::PLAN));
    }
}
