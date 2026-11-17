<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Domain\Service;

use App\Planning\Domain\Exception\MissingCatalogData;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\PlanRequirements;
use App\Planning\Domain\Model\ProductRequirement;
use App\Planning\Domain\Service\MealPlanDecomposer;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Planning\InMemoryCatalogReader;
use PHPUnit\Framework\TestCase;

final class MealPlanDecomposerTest extends TestCase
{
    private const string PLAN = '0193a5e0-7b1c-7a3e-9f00-000000000001';
    private const string BOWL = '0193a5e0-7b1c-7a3e-9f00-0000000000d1';
    private const string SOUP = '0193a5e0-7b1c-7a3e-9f00-0000000000d2';
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string OIL = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string TOMATO = '0193a5e0-7b1c-7a3e-9f00-0000000000a3';
    private const string SALT_A = '0193a5e0-7b1c-7a3e-9f00-0000000000a4';
    private const string SALT_B = '0193a5e0-7b1c-7a3e-9f00-0000000000a5';

    private InMemoryCatalogReader $catalog;

    private int $entries = 0;

    protected function setUp(): void
    {
        $this->catalog = (new InMemoryCatalogReader())
            ->withProduct(self::RICE, 'Rice')
            ->withProduct(self::OIL, 'olive oil', MeasurementCategory::Volume)
            ->withProduct(self::TOMATO, 'Tomato');
    }

    public function testDishProductLinesAreMultipliedByThePlannedPortionsInOneCallPerKind(): void
    {
        $this->catalog->withDish(self::BOWL, [self::RICE => $this->g('150'), self::OIL => Quantity::of('10', Unit::Millilitre)]);

        $result = $this->decompose([[self::BOWL, 2], [self::BOWL, 1]]);

        self::assertSame(['olive oil' => '30 ml', 'Rice' => '450 g'], self::products($result));
        self::assertSame([], $result->components);
        self::assertSame(['dishCompositions', 'products'], $this->catalog->calls());
    }

    public function testTheSameProductFromTwoDishesIsSummedInTheCanonicalUnit(): void
    {
        $this->catalog
            ->withDish(self::BOWL, [self::RICE => Quantity::of('0.5', Unit::Kilogram)])
            ->withDish(self::SOUP, [self::RICE => $this->g('300')]);

        self::assertSame(['Rice' => '800 g'], self::products($this->decompose([[self::BOWL, 1], [self::SOUP, 1]])));
    }

    public function testResultsAreSortedByNameIgnoringCaseAndThenById(): void
    {
        $this->catalog
            ->withProduct(self::SALT_B, 'salt')
            ->withProduct(self::SALT_A, 'Salt')
            ->withDish(self::BOWL, [self::SALT_B => $this->g('2'), self::TOMATO => $this->g('1'), self::SALT_A => $this->g('1'), self::OIL => Quantity::of('1', Unit::Millilitre)]);

        $result = $this->decompose([[self::BOWL, 1]]);

        self::assertSame(['olive oil', 'Salt', 'salt', 'Tomato'], array_map(static fn (ProductRequirement $p): string => $p->productName, $result->products));
    }

    public function testAnEmptyPlanNeedsNothingAndAsksTheCatalogNothing(): void
    {
        $result = $this->decompose([]);

        self::assertSame([[], []], [$result->products, $result->components]);
        self::assertSame([], $this->catalog->calls());
    }

    public function testAFinalizedPlanIsDecomposedLikeADraft(): void
    {
        $this->catalog->withDish(self::BOWL, [self::RICE => $this->g('100')]);
        $plan = $this->plan([[self::BOWL, 1]]);
        $plan->finalize(new \DateTimeImmutable('2026-11-17'));

        self::assertSame(['Rice' => '100 g'], self::products((new MealPlanDecomposer($this->catalog))->decompose($plan)));
    }

    public function testAMissingDishIsAnIntegrityErrorNamingTheId(): void
    {
        $this->assertMissing(self::SOUP, fn () => $this->decompose([[self::SOUP, 1]]));
    }

    public function testAMissingProductIsAnIntegrityErrorNamingTheId(): void
    {
        $this->catalog->withDish(self::BOWL, [self::SALT_A => $this->g('1')]);

        $this->assertMissing(self::SALT_A, fn () => $this->decompose([[self::BOWL, 1]]));
    }

    /**
     * @param list<array{string, int}> $entries dish id and portions, all on the first day
     */
    private function decompose(array $entries): PlanRequirements
    {
        return (new MealPlanDecomposer($this->catalog))->decompose($this->plan($entries));
    }

    /**
     * @param list<array{string, int}> $entries
     */
    private function plan(array $entries): MealPlan
    {
        $day = new \DateTimeImmutable('2026-11-23');
        $plan = MealPlan::create(new MealPlanId(self::PLAN), $day, new \DateTimeImmutable('2026-11-17'), new \DateTimeImmutable('2026-11-17'));
        foreach ($entries as [$dish, $portions]) {
            $id = new MealPlanEntryId(\sprintf('0193a5e0-7b1c-7a3e-9f00-%012d', ++$this->entries));
            $plan->addEntry($id, $day, new DishId($dish), $portions, new \DateTimeImmutable('2026-11-17'));
        }

        return $plan;
    }

    private function g(string $amount): Quantity
    {
        return Quantity::of($amount, Unit::Gram);
    }

    /**
     * @return array<string, string> product name => quantity, in result order
     */
    private static function products(PlanRequirements $result): array
    {
        $map = [];
        foreach ($result->products as $product) {
            $map[$product->productName] = (string) $product->quantity;
        }

        return $map;
    }

    /**
     * MissingCatalogData is a LogicException outside the shared DomainException
     * family by declaration; PHPStan checks that, so only the message is asserted.
     */
    private function assertMissing(string $id, callable $action): void
    {
        try {
            $action();
        } catch (MissingCatalogData $e) {
            self::assertStringContainsString($id, $e->getMessage());

            return;
        }

        self::fail('Expected MissingCatalogData for '.$id);
    }
}
