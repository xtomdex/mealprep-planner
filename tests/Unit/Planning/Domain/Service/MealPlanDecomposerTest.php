<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Domain\Service;

use App\Planning\Domain\Exception\MissingCatalogData;
use App\Planning\Domain\Exception\NestingDepthExceeded;
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
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';
    private const string DRESSING = '0193a5e0-7b1c-7a3e-9f00-0000000000c2';
    private const string PESTO = '0193a5e0-7b1c-7a3e-9f00-0000000000c3';
    private const string BASIL_OIL = '0193a5e0-7b1c-7a3e-9f00-0000000000c4';
    private const string BASIL = '0193a5e0-7b1c-7a3e-9f00-0000000000a6';
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
            ->withProduct(self::TOMATO, 'Tomato')
            ->withProduct(self::BASIL, 'Basil');
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

    public function testADirectComponentContributesItsBomScaledByNeedOverYield(): void
    {
        $this->catalog
            ->withComponent(self::SAUCE, 'Tomato sauce', 4, [self::TOMATO => $this->g('800'), self::OIL => Quantity::of('40', Unit::Millilitre)])
            ->withDish(self::BOWL, [self::RICE => $this->g('100')], [self::SAUCE => '1']);

        $result = $this->decompose([[self::BOWL, 2]]);

        self::assertSame(['olive oil' => '20 ml', 'Rice' => '200 g', 'Tomato' => '400 g'], self::products($result));
        self::assertSame(['dishCompositions', 'componentBoms', 'products'], $this->catalog->calls());
    }

    public function testAFractionalFactorIsAppliedAfterMultiplyingSoNoPrecisionIsLost(): void
    {
        $this->catalog
            ->withComponent(self::SAUCE, 'Tomato sauce', 3, [self::TOMATO => Quantity::of('0.3', Unit::Kilogram)])
            ->withDish(self::BOWL, [], [self::SAUCE => '1']);

        self::assertSame(['Tomato' => '100 g'], self::products($this->decompose([[self::BOWL, 1]])));
    }

    public function testComponentsCarryTheirNeededPortionsSummedOverDishes(): void
    {
        $this->catalog
            ->withComponent(self::SAUCE, 'Tomato sauce', 4, [self::TOMATO => $this->g('400')])
            ->withComponent(self::DRESSING, 'dressing', 10, [self::OIL => Quantity::of('100', Unit::Millilitre)])
            ->withDish(self::BOWL, [], [self::SAUCE => '0.5', self::DRESSING => '1'])
            ->withDish(self::SOUP, [], [self::SAUCE => '0.25']);

        $result = $this->decompose([[self::BOWL, 3], [self::SOUP, 2]]);

        self::assertSame(['dressing' => '3 portion', 'Tomato sauce' => '2 portion'], self::components($result));
        self::assertSame(['olive oil' => '30 ml', 'Tomato' => '200 g'], self::products($result));
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

    public function testPortionsPropagateThroughAParentToItsChild(): void
    {
        $this->catalog
            ->withComponent(self::SAUCE, 'Tomato sauce', 2, [self::TOMATO => $this->g('200')], [self::PESTO => '1'])
            ->withComponent(self::PESTO, 'Pesto', 4, [self::BASIL => $this->g('40')])
            ->withDish(self::BOWL, [], [self::SAUCE => '1']);

        $result = $this->decompose([[self::BOWL, 3]]);

        self::assertSame(['Pesto' => '1.5 portion', 'Tomato sauce' => '3 portion'], self::components($result));
        self::assertSame(['Basil' => '15 g', 'Tomato' => '300 g'], self::products($result));
        self::assertSame(['dishCompositions', 'componentBoms', 'componentBoms', 'products'], $this->catalog->calls());
    }

    public function testTwoLevelsOfThirdsStayExactBecauseTheOnlyDivisionComesLast(): void
    {
        $this->catalog
            ->withComponent(self::SAUCE, 'Tomato sauce', 3, [], [self::PESTO => '1'])
            ->withComponent(self::PESTO, 'Pesto', 3, [self::BASIL => $this->g('900')])
            ->withDish(self::BOWL, [], [self::SAUCE => '1']);

        self::assertSame(['Basil' => '100 g'], self::products($this->decompose([[self::BOWL, 1]])));
    }

    public function testAProductReachedThroughDishParentAndChildIsOneSummedLine(): void
    {
        $this->catalog
            ->withComponent(self::SAUCE, 'Tomato sauce', 1, [self::OIL => Quantity::of('10', Unit::Millilitre)], [self::PESTO => '2'])
            ->withComponent(self::PESTO, 'Pesto', 4, [self::OIL => Quantity::of('20', Unit::Millilitre)])
            ->withDish(self::BOWL, [self::OIL => Quantity::of('0.01', Unit::Litre)], [self::SAUCE => '1']);

        self::assertSame(['olive oil' => '30 ml'], self::products($this->decompose([[self::BOWL, 1]])));
    }

    public function testAComponentUsedDirectlyAndAsAChildIsFetchedOnceAndSummed(): void
    {
        $this->catalog
            // Pesto is registered first, so the fake returns it before its
            // parent: its products must still wait for the parent's share.
            ->withComponent(self::PESTO, 'Pesto', 4, [self::BASIL => $this->g('40')])
            ->withComponent(self::SAUCE, 'Tomato sauce', 1, [], [self::PESTO => '1'])
            ->withDish(self::BOWL, [], [self::SAUCE => '1', self::PESTO => '1']);

        $result = $this->decompose([[self::BOWL, 1]]);

        self::assertSame(['Pesto' => '2 portion', 'Tomato sauce' => '1 portion'], self::components($result));
        self::assertSame(['Basil' => '20 g'], self::products($result));
        self::assertSame(['dishCompositions', 'componentBoms', 'products'], $this->catalog->calls());
    }

    public function testAGrandchildIsRefusedNamingTheChildAndItsParent(): void
    {
        $this->catalog
            ->withComponent(self::SAUCE, 'Tomato sauce', 1, [], [self::PESTO => '1'])
            ->withComponent(self::PESTO, 'Pesto', 1, [], [self::BASIL_OIL => '1'])
            ->withComponent(self::BASIL_OIL, 'Basil oil', 1, [self::BASIL => $this->g('10')])
            ->withDish(self::BOWL, [], [self::SAUCE => '1']);

        $this->expectException(NestingDepthExceeded::class);
        $this->expectExceptionMessage(\sprintf('Component %s is used inside component %s', self::PESTO, self::SAUCE));

        $this->decompose([[self::BOWL, 1]]);
    }

    public function testAMissingChildComponentIsAnIntegrityErrorNamingTheId(): void
    {
        $this->catalog
            ->withComponent(self::SAUCE, 'Tomato sauce', 1, [], [self::PESTO => '1'])
            ->withDish(self::BOWL, [], [self::SAUCE => '1']);

        $this->assertMissing(self::PESTO, fn () => $this->decompose([[self::BOWL, 1]]));
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

    public function testAMissingComponentIsAnIntegrityErrorNamingTheId(): void
    {
        $this->catalog->withDish(self::BOWL, [], [self::SAUCE => '1']);

        $this->assertMissing(self::SAUCE, fn () => $this->decompose([[self::BOWL, 1]]));
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
     * @return array<string, string> component name => portions, in result order
     */
    private static function components(PlanRequirements $result): array
    {
        $map = [];
        foreach ($result->components as $component) {
            $map[$component->componentName] = (string) $component->portions;
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
