<?php

declare(strict_types=1);

namespace App\Tests\Contract\Catalog;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Repository\ComponentRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Persistence\RepositoryContract;

abstract class ComponentRepositoryContract extends RepositoryContract
{
    abstract protected function repository(): ComponentRepository;

    public function testAnAddedComponentIsReturnedByGetWithItsLinesInOrder(): void
    {
        $tomato = $this->existingProduct();
        $spices = $this->added('Spice mix', [IngredientRef::product($this->existingProduct())]);
        $this->flushAndClear();
        $id = $this->added('Tomato sauce', [IngredientRef::product($tomato), IngredientRef::component($spices)]);
        $this->flushAndClear();

        $component = $this->repository()->get($id);

        self::assertSame('Tomato sauce', $component->name());
        self::assertSame(2, $component->yieldPortions());
        self::assertSame(
            [[1, (string) $tomato, '100 g'], [2, (string) $spices, '100 g']],
            array_map(
                static fn ($line): array => [$line->position(), $line->ref()->isProduct() ? (string) $line->ref()->productId() : (string) $line->ref()->componentId(), (string) $line->quantity()],
                $component->ingredients(),
            ),
        );
    }

    public function testGetOfAnUnknownIdThrowsNotFound(): void
    {
        $id = self::nextId();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage(\sprintf('Component "%s" was not found.', $id));

        $this->repository()->get(new ComponentId($id));
    }

    public function testFindByIdsReturnsOnlyTheExistingComponentsInAnyOrder(): void
    {
        $a = $this->added('Cooked rice', [IngredientRef::product($this->existingProduct())]);
        $b = $this->added('Tomato sauce', [IngredientRef::product($this->existingProduct())]);
        $this->added('Spice mix', [IngredientRef::product($this->existingProduct())]);
        $this->flushAndClear();

        $found = $this->repository()->findByIds([$b, new ComponentId(self::nextId()), $a]);

        self::assertSame(self::sorted([$a, $b]), self::sorted(array_map(static fn (Component $c): ComponentId => $c->id(), $found)));
    }

    public function testFindByIdsOfNoIdsIsEmpty(): void
    {
        self::assertSame([], $this->repository()->findByIds([]));
    }

    public function testAllReturnsEveryComponent(): void
    {
        $ids = [
            $this->added('Cooked rice', [IngredientRef::product($this->existingProduct())]),
            $this->added('Tomato sauce', [IngredientRef::product($this->existingProduct())]),
        ];
        $this->flushAndClear();

        self::assertSame(self::sorted($ids), self::sorted(array_map(static fn (Component $c): ComponentId => $c->id(), $this->repository()->all())));
    }

    public function testAComponentUsedOnAnotherComponentsLineIsReferencedAsChild(): void
    {
        $child = $this->added('Spice mix', [IngredientRef::product($this->existingProduct())]);
        $this->flushAndClear();
        $parent = $this->added('Tomato sauce', [IngredientRef::product($this->existingProduct()), IngredientRef::component($child)]);
        $this->flushAndClear();

        self::assertTrue($this->repository()->isReferencedAsChild($child));
        self::assertFalse($this->repository()->isReferencedAsChild($parent));
        self::assertFalse($this->repository()->isReferencedAsChild(new ComponentId(self::nextId())));
    }

    /**
     * @param non-empty-list<IngredientRef> $refs each line takes 100 g
     */
    private function added(string $name, array $refs): ComponentId
    {
        $id = new ComponentId(self::nextId());
        $lines = array_map(static fn (IngredientRef $ref): IngredientLine => new IngredientLine($ref, Quantity::of('100', Unit::Gram)), $refs);
        $this->repository()->add(Component::create($id, $name, 2, $lines, new \DateTimeImmutable()));

        return $id;
    }
}
