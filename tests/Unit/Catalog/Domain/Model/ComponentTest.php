<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use PHPUnit\Framework\TestCase;

final class ComponentTest extends TestCase
{
    private const string ID = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-000000000002';
    private const string WATER = '0193a5e0-7b1c-7a3e-9f00-000000000003';
    private const string STOCK = '0193a5e0-7b1c-7a3e-9f00-000000000004';

    public function testCreateTurnsLinesIntoIngredientsNumberedFromOne(): void
    {
        $now = new \DateTimeImmutable('2026-11-11 10:00:00');
        $rice = new IngredientLine(IngredientRef::product(new ProductId(self::RICE)), Quantity::of('500', Unit::Gram));
        $water = new IngredientLine(IngredientRef::product(new ProductId(self::WATER)), Quantity::of('1', Unit::Litre));

        $component = Component::create(new ComponentId(self::ID), ' Cooked rice ', 4, [$rice, $water], $now);

        self::assertSame('Cooked rice', $component->name());
        self::assertSame(4, $component->yieldPortions());
        self::assertSame($now, $component->createdAt());
        self::assertSame($now, $component->updatedAt());

        $ingredients = $component->ingredients();
        self::assertCount(2, $ingredients);
        self::assertSame([1, 2], [$ingredients[0]->position(), $ingredients[1]->position()]);
        self::assertSame($component, $ingredients[0]->component());
        self::assertSame($rice->ref, $ingredients[0]->ref());
        self::assertSame($rice->quantity, $ingredients[0]->quantity());
        self::assertSame($water->ref, $ingredients[1]->ref());
    }

    public function testHasComponentLinesIsFalseForAComponentMadeOfProductsOnly(): void
    {
        $component = $this->create([$this->productLine(self::RICE)]);

        self::assertFalse($component->hasComponentLines());
    }

    public function testHasComponentLinesIsTrueWhenAnyLinePointsAtAComponent(): void
    {
        $component = $this->create([
            $this->productLine(self::RICE),
            new IngredientLine(IngredientRef::component(new ComponentId(self::STOCK)), Quantity::of('1', Unit::Portion)),
        ]);

        self::assertTrue($component->hasComponentLines());
    }

    public function testCmp01RejectsABlankName(): void
    {
        $this->assertRejected('CMP-01', 'name', fn () => Component::create(new ComponentId(self::ID), ' ', 1, [$this->productLine(self::RICE)], new \DateTimeImmutable()));
    }

    public function testCmp02RejectsAComponentWithoutLines(): void
    {
        $this->assertRejected('CMP-02', 'ingredients', fn () => $this->create([]));
    }

    public function testCmp03RejectsZeroPortions(): void
    {
        $this->assertRejected('CMP-03', 'yieldPortions', fn () => Component::create(new ComponentId(self::ID), 'Cooked rice', 0, [$this->productLine(self::RICE)], new \DateTimeImmutable()));
    }

    public function testCmp04RejectsTheSameIngredientOnTwoLinesAndNamesBothLines(): void
    {
        $lines = [$this->productLine(self::RICE), $this->productLine(self::WATER), $this->productLine(self::RICE)];

        $e = $this->assertRejected('CMP-04', 'ingredients', fn () => $this->create($lines));
        self::assertStringContainsString('Line 3 repeats the ingredient of line 1 (product '.self::RICE.')', $e->getMessage());
    }

    public function testCmp04AllowsTheSameUuidAsAProductAndAsAComponent(): void
    {
        $component = $this->create([
            $this->productLine(self::RICE),
            new IngredientLine(IngredientRef::component(new ComponentId(self::RICE)), Quantity::of('1', Unit::Portion)),
        ]);

        self::assertCount(2, $component->ingredients());
    }

    private function productLine(string $productId): IngredientLine
    {
        return new IngredientLine(IngredientRef::product(new ProductId($productId)), Quantity::of('100', Unit::Gram));
    }

    /**
     * @param list<IngredientLine> $lines
     */
    private function create(array $lines): Component
    {
        return Component::create(new ComponentId(self::ID), 'Cooked rice', 4, $lines, new \DateTimeImmutable());
    }

    private function assertRejected(string $code, string $field, callable $action): ValidationException
    {
        try {
            $action();
        } catch (ValidationException $e) {
            self::assertSame($code, $e->errorCode());
            self::assertSame($field, $e->field());

            return $e;
        }

        self::fail(\sprintf('Expected a ValidationException %s.', $code));
    }
}
