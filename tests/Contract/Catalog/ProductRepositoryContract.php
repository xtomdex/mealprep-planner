<?php

declare(strict_types=1);

namespace App\Tests\Contract\Catalog;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Tests\Support\Persistence\RepositoryContract;

/**
 * What every ProductRepository does, whatever stores it. The in-memory fake
 * and the Doctrine repository both run these tests, so a handler tested
 * against the fake behaves the same against the database.
 */
abstract class ProductRepositoryContract extends RepositoryContract
{
    abstract protected function repository(): ProductRepository;

    public function testAnAddedProductIsReturnedByGet(): void
    {
        $id = new ProductId(self::nextId());
        $this->repository()->add(Product::create($id, 'Rice', MeasurementCategory::Weight, new \DateTimeImmutable()));
        $this->flushAndClear();

        $product = $this->repository()->get($id);

        self::assertTrue($product->id()->equals($id));
        self::assertSame('Rice', $product->name());
        self::assertSame(MeasurementCategory::Weight, $product->measurementCategory());
    }

    public function testGetOfAnUnknownIdThrowsNotFound(): void
    {
        $id = self::nextId();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage(\sprintf('Product "%s" was not found.', $id));

        $this->repository()->get(new ProductId($id));
    }

    public function testFindByIdsReturnsOnlyTheExistingProductsInAnyOrder(): void
    {
        [$a, $b] = [$this->added('Rice'), $this->added('Tomato')];
        $this->added('Olive oil');
        $this->flushAndClear();

        $found = $this->repository()->findByIds([$b, new ProductId(self::nextId()), $a, $b]);

        self::assertSame(self::sorted([$a, $b]), self::sorted(array_map(static fn (Product $p): ProductId => $p->id(), $found)));
    }

    public function testFindByIdsOfNoIdsIsEmpty(): void
    {
        self::assertSame([], $this->repository()->findByIds([]));
    }

    public function testAllReturnsEveryProduct(): void
    {
        $ids = [$this->added('Rice'), $this->added('Tomato')];
        $this->flushAndClear();

        self::assertSame(self::sorted($ids), self::sorted(array_map(static fn (Product $p): ProductId => $p->id(), $this->repository()->all())));
    }

    private function added(string $name): ProductId
    {
        $id = new ProductId(self::nextId());
        $this->repository()->add(Product::create($id, $name, MeasurementCategory::Weight, new \DateTimeImmutable()));

        return $id;
    }
}
