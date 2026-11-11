<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use PHPUnit\Framework\TestCase;

final class DishTest extends TestCase
{
    private const string ID = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-000000000002';
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-000000000003';

    public function testCreateTurnsLinesIntoIngredientsNumberedFromOne(): void
    {
        $now = new \DateTimeImmutable('2026-11-11 10:00:00');
        $rice = new IngredientLine(IngredientRef::component(new ComponentId(self::RICE)), Quantity::of('1', Unit::Portion));
        $sauce = new IngredientLine(IngredientRef::product(new ProductId(self::SAUCE)), Quantity::of('10', Unit::Millilitre));

        $dish = Dish::create(new DishId(self::ID), ' Chicken with rice ', [$rice, $sauce], $now);

        self::assertSame('Chicken with rice', $dish->name());
        self::assertSame($now, $dish->createdAt());
        self::assertSame($now, $dish->updatedAt());

        $ingredients = $dish->ingredients();
        self::assertCount(2, $ingredients);
        self::assertSame([1, 2], [$ingredients[0]->position(), $ingredients[1]->position()]);
        self::assertSame($dish, $ingredients[1]->dish());
        self::assertSame($rice->ref, $ingredients[0]->ref());
        self::assertSame($sauce->quantity, $ingredients[1]->quantity());
    }

    public function testDsh01RejectsABlankName(): void
    {
        $this->assertRejected('DSH-01', 'name', fn () => Dish::create(new DishId(self::ID), '', [$this->line()], new \DateTimeImmutable()));
    }

    public function testDsh02RejectsADishWithoutLines(): void
    {
        $this->assertRejected('DSH-02', 'ingredients', static fn () => Dish::create(new DishId(self::ID), 'Chicken with rice', [], new \DateTimeImmutable()));
    }

    public function testDsh03RejectsTheSameIngredientOnTwoLines(): void
    {
        $this->assertRejected('DSH-03', 'ingredients', fn () => Dish::create(new DishId(self::ID), 'Chicken with rice', [$this->line(), $this->line()], new \DateTimeImmutable()));
    }

    private function line(): IngredientLine
    {
        return new IngredientLine(IngredientRef::component(new ComponentId(self::RICE)), Quantity::of('1', Unit::Portion));
    }

    private function assertRejected(string $code, string $field, callable $action): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            self::assertSame($code, $e->errorCode());
            self::assertSame($field, $e->field());

            return;
        }

        self::fail(\sprintf('Expected a ValidationException %s.', $code));
    }
}
