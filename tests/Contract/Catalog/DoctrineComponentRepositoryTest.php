<?php

declare(strict_types=1);

namespace App\Tests\Contract\Catalog;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Repository\ComponentRepository;
use App\Catalog\Infrastructure\Persistence\DoctrineComponentRepository;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Persistence\DoctrineContract;

final class DoctrineComponentRepositoryTest extends ComponentRepositoryContract
{
    use DoctrineContract;

    protected function repository(): ComponentRepository
    {
        return new DoctrineComponentRepository($this->entityManager());
    }

    /**
     * The N+1 check on the real database: three components with two lines
     * each come back in one statement, and reading every line afterwards
     * sends nothing more.
     */
    public function testFindByIdsLoadsComponentsAndLinesInOneQuery(): void
    {
        $ids = [];
        foreach (['Cooked rice', 'Tomato sauce', 'Spice mix'] as $name) {
            $ids[] = $id = new ComponentId(self::nextId());
            $this->repository()->add(Component::create($id, $name, 1, [
                new IngredientLine(IngredientRef::product($this->existingProduct()), Quantity::of('100', Unit::Gram)),
                new IngredientLine(IngredientRef::product($this->existingProduct()), Quantity::of('5', Unit::Gram)),
            ], new \DateTimeImmutable()));
        }
        $this->flushAndClear();

        $components = [];
        self::assertSame(1, $this->queriesOf(function () use ($ids, &$components): void {
            $components = $this->repository()->findByIds($ids);
        }));
        self::assertCount(3, $components);

        $amounts = [];
        self::assertSame(0, $this->queriesOf(static function () use ($components, &$amounts): void {
            foreach ($components as $component) {
                foreach ($component->ingredients() as $line) {
                    $amounts[] = $line->quantity()->amount;
                }
            }
        }));
        self::assertSame(['100', '5', '100', '5', '100', '5'], $amounts, 'Lines in position order inside each component.');
    }

    public function testFindByIdsOfNoIdsSendsNoQuery(): void
    {
        self::assertSame(0, $this->queriesOf(fn () => $this->repository()->findByIds([])));
    }
}
