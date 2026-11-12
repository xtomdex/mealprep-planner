<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Domain\Model;

use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\MealPlanStatus;
use App\Planning\Domain\Model\ShoppingListId;
use App\Shared\Domain\Identity\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlanningIdsTest extends TestCase
{
    private const string VALUE = '0193a5e0-7b1c-7a3e-9f00-0123456789ab';

    /**
     * @return iterable<string, array{class-string<Uuid>}>
     */
    public static function idClasses(): iterable
    {
        yield 'meal plan' => [MealPlanId::class];
        yield 'meal plan entry' => [MealPlanEntryId::class];
        yield 'shopping list' => [ShoppingListId::class];
    }

    /**
     * @param class-string<Uuid> $class
     */
    #[DataProvider('idClasses')]
    public function testConstructsFromACanonicalUuid(string $class): void
    {
        $id = new $class(self::VALUE);

        self::assertSame(self::VALUE, $id->value);
        self::assertTrue($id->equals(new $class(self::VALUE)));
    }

    /**
     * @param class-string<Uuid> $class
     */
    #[DataProvider('idClasses')]
    public function testRejectsAMalformedValue(string $class): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new $class('not-a-uuid');
    }

    public function testIdsOfDifferentTypesNeverEqualEvenWithTheSameValue(): void
    {
        self::assertFalse((new MealPlanId(self::VALUE))->equals(new ShoppingListId(self::VALUE)));
        self::assertFalse((new MealPlanId(self::VALUE))->equals(new MealPlanEntryId(self::VALUE)));
        self::assertFalse((new ShoppingListId(self::VALUE))->equals(new MealPlanEntryId(self::VALUE)));
    }

    public function testStatusValuesAreStable(): void
    {
        // The backing values end up in the database and in API responses.
        self::assertSame(['draft', 'finalized'], array_map(static fn (MealPlanStatus $s): string => $s->value, MealPlanStatus::cases()));
    }
}
