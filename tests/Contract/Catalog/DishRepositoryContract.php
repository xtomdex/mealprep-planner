<?php

declare(strict_types=1);

namespace App\Tests\Contract\Catalog;

use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Repository\DishRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Persistence\RepositoryContract;

abstract class DishRepositoryContract extends RepositoryContract
{
    abstract protected function repository(): DishRepository;

    public function testAnAddedDishIsReturnedByGetWithItsLinesInOrder(): void
    {
        [$rice, $oil] = [$this->existingProduct(), $this->existingProduct()];
        $id = $this->added('Rice bowl', [$rice, $oil]);
        $this->flushAndClear();

        $dish = $this->repository()->get($id);

        self::assertSame('Rice bowl', $dish->name());
        self::assertSame(
            [[1, (string) $rice], [2, (string) $oil]],
            array_map(static fn ($line): array => [$line->position(), (string) $line->ref()->productId()], $dish->ingredients()),
        );
    }

    public function testGetOfAnUnknownIdThrowsNotFound(): void
    {
        $id = self::nextId();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage(\sprintf('Dish "%s" was not found.', $id));

        $this->repository()->get(new DishId($id));
    }

    public function testAllReturnsEveryDish(): void
    {
        $ids = [$this->added('Rice bowl', [$this->existingProduct()]), $this->added('Salad', [$this->existingProduct()])];
        $this->flushAndClear();

        self::assertSame(self::sorted($ids), self::sorted(array_map(static fn (Dish $d): DishId => $d->id(), $this->repository()->all())));
    }

    /**
     * @param non-empty-list<ProductId> $products one 10 g line each
     */
    protected function added(string $name, array $products): DishId
    {
        $id = new DishId(self::nextId());
        $lines = array_map(static fn (ProductId $product): IngredientLine => new IngredientLine(IngredientRef::product($product), Quantity::of('10', Unit::Gram)), $products);
        $this->repository()->add(Dish::create($id, $name, $lines, new \DateTimeImmutable()));

        return $id;
    }
}
