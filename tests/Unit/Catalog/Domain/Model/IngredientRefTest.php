<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\RefKind;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;
use PHPUnit\Framework\TestCase;

final class IngredientRefTest extends TestCase
{
    private const string ID = '0193a5e0-7b1c-7a3e-9f00-0123456789ab';
    private const string OTHER_ID = '0193a5e0-7b1c-7a3e-9f00-0123456789ac';

    public function testRefKindHasExactlyProductAndComponent(): void
    {
        self::assertSame(['product', 'component'], array_column(RefKind::cases(), 'value'));
    }

    public function testProductReference(): void
    {
        $id = new ProductId(self::ID);
        $ref = IngredientRef::product($id);

        self::assertSame(RefKind::Product, $ref->kind());
        self::assertTrue($ref->isProduct());
        self::assertFalse($ref->isComponent());
        self::assertSame($id, $ref->productId());
    }

    public function testComponentReference(): void
    {
        $id = new ComponentId(self::ID);
        $ref = IngredientRef::component($id);

        self::assertSame(RefKind::Component, $ref->kind());
        self::assertTrue($ref->isComponent());
        self::assertFalse($ref->isProduct());
        self::assertSame($id, $ref->componentId());
    }

    public function testAskingAComponentReferenceForAProductIdIsALogicError(): void
    {
        $ref = IngredientRef::component(new ComponentId(self::ID));

        $this->expectException(\LogicException::class);

        $ref->productId();
    }

    public function testAskingAProductReferenceForAComponentIdIsALogicError(): void
    {
        $ref = IngredientRef::product(new ProductId(self::ID));

        $this->expectException(\LogicException::class);

        $ref->componentId();
    }

    public function testReferencesToTheSameProductAreEqual(): void
    {
        $a = IngredientRef::product(new ProductId(self::ID));
        $b = IngredientRef::product(new ProductId(self::ID));

        self::assertTrue($a->equals($b));
    }

    public function testReferencesToTheSameComponentAreEqual(): void
    {
        $a = IngredientRef::component(new ComponentId(self::ID));
        $b = IngredientRef::component(new ComponentId(self::ID));

        self::assertTrue($a->equals($b));
    }

    public function testReferencesToDifferentTargetsOfTheSameKindAreNotEqual(): void
    {
        $product = IngredientRef::product(new ProductId(self::ID));
        $component = IngredientRef::component(new ComponentId(self::ID));

        self::assertFalse($product->equals(IngredientRef::product(new ProductId(self::OTHER_ID))));
        self::assertFalse($component->equals(IngredientRef::component(new ComponentId(self::OTHER_ID))));
    }

    public function testProductAndComponentReferencesAreNotEqualEvenWithTheSameUuid(): void
    {
        $product = IngredientRef::product(new ProductId(self::ID));
        $component = IngredientRef::component(new ComponentId(self::ID));

        self::assertFalse($product->equals($component));
        self::assertFalse($component->equals($product));
    }
}
