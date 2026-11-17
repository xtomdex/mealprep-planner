<?php

declare(strict_types=1);

namespace App\Planning\Domain\Service;

use App\Planning\Domain\Catalog\ProductLine;
use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;

/**
 * Running totals of one decomposition: product amounts.
 *
 * A line is always added with a scale given as numerator / denominator: the
 * dish portions over 1 for a dish line; recipe lines will use a component's
 * need over its yield. The one division happens when the amount is produced.
 *
 * @internal mutable working state of MealPlanDecomposer::decompose(), one per call
 */
final class RequirementTally
{
    /** @var array<string, ProductId> */
    private array $productIds = [];

    /** @var array<string, Quantity> always in the canonical unit */
    private array $productNeed = [];

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
        $this->productNeed[$key] = isset($this->productNeed[$key]) ? $this->productNeed[$key]->add($need) : $need;
    }

    /**
     * @return list<ProductId>
     */
    public function productIds(): array
    {
        return array_values($this->productIds);
    }

    public function productNeed(ProductId $id): Quantity
    {
        return $this->productNeed[$id->value];
    }
}
