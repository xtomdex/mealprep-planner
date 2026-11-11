<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\Catalog;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\Product;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Catalog\InMemoryComponentRepository;
use App\Tests\Support\Catalog\InMemoryProductRepository;
use PHPUnit\Framework\TestCase;

/**
 * The fakes stand in for real repositories in other tests, so their
 * contract is pinned here: a fake that lies makes every test built on it lie.
 */
final class InMemoryCatalogRepositoriesTest extends TestCase
{
    private const string A = '0193a5e0-7b1c-7a3e-9f00-00000000000a';
    private const string B = '0193a5e0-7b1c-7a3e-9f00-00000000000b';
    private const string C = '0193a5e0-7b1c-7a3e-9f00-00000000000c';

    public function testProductGetReturnsWhatWasAddedAndThrowsForAnUnknownId(): void
    {
        $repository = new InMemoryProductRepository();
        $product = Product::create(new ProductId(self::A), 'Rice', MeasurementCategory::Weight, new \DateTimeImmutable());
        $repository->add($product);

        self::assertSame($product, $repository->get(new ProductId(self::A)));
        self::assertSame([$product], $repository->all());

        $this->expectException(NotFoundException::class);
        $repository->get(new ProductId(self::B));
    }

    public function testProductFindByIdsSkipsUnknownIdsAndDuplicates(): void
    {
        $repository = new InMemoryProductRepository();
        $product = Product::create(new ProductId(self::A), 'Rice', MeasurementCategory::Weight, new \DateTimeImmutable());
        $repository->add($product);

        self::assertSame([$product], $repository->findByIds([new ProductId(self::A), new ProductId(self::B), new ProductId(self::A)]));
    }

    public function testComponentGetFindAndAll(): void
    {
        $repository = new InMemoryComponentRepository();
        $component = $this->component(self::A, IngredientRef::product(new ProductId(self::C)));
        $repository->add($component);

        self::assertSame($component, $repository->get(new ComponentId(self::A)));
        self::assertSame([$component], $repository->findByIds([new ComponentId(self::A), new ComponentId(self::B)]));
        self::assertSame([$component], $repository->all());

        $this->expectException(NotFoundException::class);
        $repository->get(new ComponentId(self::B));
    }

    public function testIsReferencedAsChildLooksAtComponentLinesOnly(): void
    {
        $repository = new InMemoryComponentRepository();
        // B uses component A; it also has a product line with C's uuid, which must not count for component C.
        $repository->add($this->component(self::B, IngredientRef::component(new ComponentId(self::A)), IngredientRef::product(new ProductId(self::C))));

        self::assertTrue($repository->isReferencedAsChild(new ComponentId(self::A)));
        self::assertFalse($repository->isReferencedAsChild(new ComponentId(self::B)));
        self::assertFalse($repository->isReferencedAsChild(new ComponentId(self::C)));
    }

    private function component(string $id, IngredientRef ...$refs): Component
    {
        $lines = array_map(static fn (IngredientRef $ref): IngredientLine => new IngredientLine($ref, Quantity::of('1', $ref->isProduct() ? Unit::Gram : Unit::Portion)), $refs);

        return Component::create(new ComponentId($id), 'Component', 1, array_values($lines), new \DateTimeImmutable());
    }
}
