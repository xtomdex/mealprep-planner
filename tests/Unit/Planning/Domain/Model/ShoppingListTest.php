<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Domain\Model;

use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ProductRequirement;
use App\Planning\Domain\Model\ShoppingItem;
use App\Planning\Domain\Model\ShoppingList;
use App\Planning\Domain\Model\ShoppingListId;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use PHPUnit\Framework\TestCase;

final class ShoppingListTest extends TestCase
{
    private const string LIST = '0193a5e0-7b1c-7a3e-9f00-000000000005';
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string OIL = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string EGGS = '0193a5e0-7b1c-7a3e-9f00-0000000000a3';

    public function testGenerateKeepsTheInputOrderAndNumbersItemsFromOne(): void
    {
        $now = new \DateTimeImmutable('2026-11-12 10:00');

        $list = ShoppingList::generate(new ShoppingListId(self::LIST), new MealPlanId(self::PLAN), [
            $this->requirement(self::OIL, 'Olive oil', '250', Unit::Millilitre),
            $this->requirement(self::RICE, 'Rice', '500', Unit::Gram),
        ], $now);

        self::assertTrue((new ShoppingListId(self::LIST))->equals($list->id()));
        self::assertTrue((new MealPlanId(self::PLAN))->equals($list->mealPlanId()));
        self::assertSame($now, $list->generatedAt());

        $items = $list->items();
        self::assertSame([1, 2], array_map(static fn (ShoppingItem $i): int => $i->position(), $items));
        self::assertSame(['Olive oil', 'Rice'], array_map(static fn (ShoppingItem $i): string => $i->productName(), $items));
        self::assertTrue((new ProductId(self::OIL))->equals($items[0]->productId()));
        self::assertSame($list, $items[0]->shoppingList());
    }

    public function testEveryItemIsCanonicalAndRoundedUpToAWholeNumber(): void
    {
        $list = $this->generate([
            $this->requirement(self::RICE, 'Rice', '333.33', Unit::Gram),
            $this->requirement(self::OIL, 'Olive oil', '1.2', Unit::Litre),
            $this->requirement(self::EGGS, 'Eggs', '2.5', Unit::Piece),
        ]);

        self::assertSame(['334 g', '1200 ml', '3 pcs'], array_map(static fn (ShoppingItem $i): string => (string) $i->quantity(), $list->items()));
    }

    public function testConversionHappensBeforeRoundingSoKilogramsKeepGramPrecision(): void
    {
        $list = $this->generate([$this->requirement(self::RICE, 'Rice', '1.2004', Unit::Kilogram)]);

        self::assertSame('1201 g', (string) $list->items()[0]->quantity());
    }

    public function testShl02RejectsAnEmptyRequirementList(): void
    {
        $this->assertRejected('SHL-02', fn () => $this->generate([]));
    }

    public function testShl03RejectsTheSameProductTwiceAndNamesBothItems(): void
    {
        $e = $this->assertRejected('SHL-03', fn () => $this->generate([
            $this->requirement(self::RICE, 'Rice', '100', Unit::Gram),
            $this->requirement(self::OIL, 'Olive oil', '1', Unit::Litre),
            $this->requirement(self::RICE, 'Rice', '1', Unit::Kilogram),
        ]));

        self::assertStringContainsString('Item 3 repeats the product of item 1 (product '.self::RICE.')', $e->getMessage());
    }

    /**
     * @param list<ProductRequirement> $requirements
     */
    private function generate(array $requirements): ShoppingList
    {
        return ShoppingList::generate(new ShoppingListId(self::LIST), new MealPlanId(self::PLAN), $requirements, new \DateTimeImmutable());
    }

    private function requirement(string $productId, string $name, string $amount, Unit $unit): ProductRequirement
    {
        return new ProductRequirement(new ProductId($productId), $name, Quantity::of($amount, $unit));
    }

    private function assertRejected(string $code, callable $action): ValidationException
    {
        try {
            $action();
        } catch (ValidationException $e) {
            self::assertSame($code, $e->errorCode());
            self::assertSame('items', $e->field());

            return $e;
        }

        self::fail(\sprintf('Expected a ValidationException %s.', $code));
    }
}
