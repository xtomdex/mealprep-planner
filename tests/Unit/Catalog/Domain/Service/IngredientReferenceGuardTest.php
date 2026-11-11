<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Service;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Service\IngredientReferenceGuard;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Catalog\InMemoryComponentRepository;
use App\Tests\Support\Catalog\InMemoryProductRepository;
use PHPUnit\Framework\TestCase;

/**
 * Catalog used throughout: rice (weight) and olive oil (volume); "stock" is
 * a flat component made of rice and "dressing" one made of oil; "risotto
 * base" contains stock, so it is not flat and stock is someone's child. SELF is the component under validation, not stored yet.
 */
final class IngredientReferenceGuardTest extends TestCase
{
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string OIL = '0193a5e0-7b1c-7a3e-9f00-000000000002';
    private const string STOCK = '0193a5e0-7b1c-7a3e-9f00-000000000003';
    private const string RISOTTO_BASE = '0193a5e0-7b1c-7a3e-9f00-000000000004';
    private const string SELF = '0193a5e0-7b1c-7a3e-9f00-000000000005';
    private const string DRESSING = '0193a5e0-7b1c-7a3e-9f00-000000000006';
    private const string UNKNOWN = '0193a5e0-7b1c-7a3e-9f00-0000000000ff';

    private InMemoryComponentRepository $components;
    private IngredientReferenceGuard $guard;

    protected function setUp(): void
    {
        $now = new \DateTimeImmutable('2026-11-11 10:00:00');
        $products = new InMemoryProductRepository();
        $products->add(Product::create(new ProductId(self::RICE), 'Rice', MeasurementCategory::Weight, $now));
        $products->add(Product::create(new ProductId(self::OIL), 'Olive oil', MeasurementCategory::Volume, $now));

        $this->components = new InMemoryComponentRepository();
        $this->components->add(Component::create(new ComponentId(self::STOCK), 'Stock', 4, [self::product(self::RICE, '100', Unit::Gram)], $now));
        $this->components->add(Component::create(new ComponentId(self::DRESSING), 'Dressing', 6, [self::product(self::OIL, '50', Unit::Millilitre)], $now));
        $this->components->add(Component::create(new ComponentId(self::RISOTTO_BASE), 'Risotto base', 2, [self::component(self::STOCK)], $now));

        $this->guard = new IngredientReferenceGuard($products, $this->components);
    }

    public function testDishAcceptsExistingProductsInTheirCategoryAndComponentsInPortions(): void
    {
        $this->expectNotToPerformAssertions();

        // Kilograms are fine for rice: the category matters, not the unit.
        // A dish may use a nested component: it is the end of the chain, not part of a BoM.
        $this->guard->assertValidForDish([
            self::product(self::RICE, '0.2', Unit::Kilogram),
            self::product(self::OIL, '10', Unit::Millilitre),
            self::component(self::RISOTTO_BASE),
        ]);
    }

    public function testIng01RejectsAnUnknownProductAndNamesTheLine(): void
    {
        $e = $this->assertRejected('ING-01', fn () => $this->guard->assertValidForDish([
            self::product(self::RICE, '100', Unit::Gram),
            self::product(self::UNKNOWN, '100', Unit::Gram),
        ]));

        self::assertStringStartsWith('Line 2 (product '.self::UNKNOWN.')', $e->getMessage());
    }

    public function testIng01RejectsAnUnknownComponent(): void
    {
        $e = $this->assertRejected('ING-01', fn () => $this->guard->assertValidForComponent(new ComponentId(self::SELF), [self::component(self::UNKNOWN)]));

        self::assertStringStartsWith('Line 1 (component '.self::UNKNOWN.')', $e->getMessage());
    }

    public function testIng02RejectsAProductMeasuredInAnotherCategory(): void
    {
        $e = $this->assertRejected('ING-02', fn () => $this->guard->assertValidForDish([self::product(self::RICE, '200', Unit::Millilitre)]));

        self::assertStringContainsString('"Rice" is measured by weight, 200 ml is not', $e->getMessage());
    }

    public function testIng03RejectsAComponentMeasuredInAnythingButPortions(): void
    {
        $this->assertRejected('ING-03', fn () => $this->guard->assertValidForDish([
            new IngredientLine(IngredientRef::component(new ComponentId(self::STOCK)), Quantity::of('300', Unit::Gram)),
        ]));
    }

    public function testIng04AcceptsAFlatChildInANewComponent(): void
    {
        $this->expectNotToPerformAssertions();

        $this->guard->assertValidForComponent(new ComponentId(self::SELF), [self::product(self::OIL, '5', Unit::Millilitre), self::component(self::STOCK)]);
    }

    public function testIng04RejectsAComponentThatContainsItself(): void
    {
        $e = $this->assertRejected('ING-04', fn () => $this->guard->assertValidForComponent(new ComponentId(self::SELF), [self::component(self::SELF)]));

        self::assertStringContainsString('cannot contain itself', $e->getMessage());
    }

    public function testIng04RejectsAChildThatIsNotFlat(): void
    {
        $e = $this->assertRejected('ING-04', fn () => $this->guard->assertValidForComponent(new ComponentId(self::SELF), [self::component(self::RISOTTO_BASE)]));

        self::assertStringStartsWith('Line 1 (component '.self::RISOTTO_BASE.')', $e->getMessage());
    }

    public function testIng04RejectsComponentLinesInAComponentThatIsAlreadySomeonesChild(): void
    {
        // STOCK is a child of RISOTTO_BASE; giving it a component line would
        // make three levels. DRESSING itself is flat, so only this branch fails.
        $e = $this->assertRejected('ING-04', fn () => $this->guard->assertValidForComponent(new ComponentId(self::STOCK), [
            self::product(self::RICE, '100', Unit::Gram),
            self::component(self::DRESSING),
        ]));

        self::assertStringContainsString('used inside another component', $e->getMessage());
    }

    public function testIng04LetsAChildKeepProductOnlyLines(): void
    {
        $this->expectNotToPerformAssertions();

        $this->guard->assertValidForComponent(new ComponentId(self::STOCK), [self::product(self::RICE, '150', Unit::Gram)]);
    }

    private static function product(string $id, string $amount, Unit $unit): IngredientLine
    {
        return new IngredientLine(IngredientRef::product(new ProductId($id)), Quantity::of($amount, $unit));
    }

    private static function component(string $id): IngredientLine
    {
        return new IngredientLine(IngredientRef::component(new ComponentId($id)), Quantity::of('1', Unit::Portion));
    }

    private function assertRejected(string $code, callable $action): ValidationException
    {
        try {
            $action();
        } catch (ValidationException $e) {
            self::assertSame($code, $e->errorCode());
            self::assertSame('ingredients', $e->field());

            return $e;
        }

        self::fail(\sprintf('Expected a ValidationException %s.', $code));
    }
}
