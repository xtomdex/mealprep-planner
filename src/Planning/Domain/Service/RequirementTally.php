<?php

declare(strict_types=1);

namespace App\Planning\Domain\Service;

use App\Planning\Domain\Catalog\ComponentLine;
use App\Planning\Domain\Catalog\ProductLine;
use App\Planning\Domain\Exception\InconsistentCatalogData;
use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;

/**
 * Running totals of one decomposition: product amounts and component needs.
 *
 * A line is always added with a scale given as numerator / denominator: the
 * dish portions over 1 for a dish line, the component's need over its yield
 * for a recipe line. A component's need is kept as such a fraction as well,
 * because it is itself a share of a parent's yield; the one division happens
 * when an amount is finally produced.
 *
 * @internal mutable working state of MealPlanDecomposer::decompose(), one per call
 */
final class RequirementTally
{
    /** @var array<string, ProductId> */
    private array $productIds = [];

    /**
     * Totals are kept per measurement category, so lines that disagree on how
     * a product is measured are reported against the product's own category
     * instead of failing inside Quantity::add() as a client error (QTY-02).
     *
     * @var array<string, array<string, Quantity>> product id => category => canonical total
     */
    private array $productNeed = [];

    /** @var array<string, ComponentId> */
    private array $componentIds = [];

    /** @var array<string, array{string, int}> numerator and denominator, in portions */
    private array $componentNeed = [];

    public function addProduct(ProductLine $line, string $numerator, int $denominator): void
    {
        $canonical = $line->quantity->toCanonical();
        // Multiply first, divide last: Decimal cuts every result to six digits,
        // so 300 g × (1 / 3) is 300 × 0.333333 = 99.9999 g, while (300 × 1) / 3
        // is exactly 100 g. Converting to grams first keeps the same six digits
        // for a value a thousand times larger than in kilograms.
        $need = Quantity::of(Decimal::div(Decimal::mul($canonical->amount, $numerator), (string) $denominator), $canonical->unit);

        $key = $line->productId->value;
        $this->productIds[$key] = $line->productId;
        $category = $need->unit->category()->value;
        $total = $this->productNeed[$key][$category] ?? null;
        $this->productNeed[$key][$category] = null === $total ? $need : $total->add($need);
    }

    public function addComponent(ComponentLine $line, string $numerator, int $denominator): void
    {
        $key = $line->componentId->value;
        $added = [Decimal::mul($line->quantity->amount, $numerator), $denominator];

        $this->componentIds[$key] = $line->componentId;
        $this->componentNeed[$key] = isset($this->componentNeed[$key]) ? self::sum($this->componentNeed[$key], $added) : $added;
    }

    /**
     * @return list<ProductId>
     */
    public function productIds(): array
    {
        return array_values($this->productIds);
    }

    /**
     * @return list<ComponentId>
     */
    public function componentIds(): array
    {
        return array_values($this->componentIds);
    }

    /**
     * @throws InconsistentCatalogData when a line measured the product in another category
     */
    public function productNeed(ProductId $id, MeasurementCategory $category): Quantity
    {
        foreach ($this->productNeed[$id->value] as $need) {
            if ($need->unit->category() !== $category) {
                throw InconsistentCatalogData::categoryMismatch($id, $category, $need->unit->category());
            }
        }

        return $this->productNeed[$id->value][$category->value];
    }

    /**
     * @return array{string, int} numerator and denominator
     */
    public function componentNeed(ComponentId $id): array
    {
        return $this->componentNeed[$id->value];
    }

    public function componentPortions(ComponentId $id): Quantity
    {
        [$numerator, $denominator] = $this->componentNeed[$id->value];

        return Quantity::of(Decimal::div($numerator, (string) $denominator), Unit::Portion);
    }

    /**
     * a/b + c/d over a common denominator; equal denominators (the usual case:
     * every dish line is over 1) skip the cross-multiplication.
     *
     * @param array{string, int} $a
     * @param array{string, int} $b
     *
     * @return array{string, int}
     */
    private static function sum(array $a, array $b): array
    {
        if ($a[1] === $b[1]) {
            return [Decimal::add($a[0], $b[0]), $a[1]];
        }

        return [Decimal::add(Decimal::mul($a[0], (string) $b[1]), Decimal::mul($b[0], (string) $a[1])), $a[1] * $b[1]];
    }
}
