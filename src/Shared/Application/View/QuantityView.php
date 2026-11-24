<?php

declare(strict_types=1);

namespace App\Shared\Application\View;

use App\Shared\Domain\Measurement\Quantity;

/**
 * An amount and its unit as a caller sees them: {"amount": "150", "unit": "g"}.
 *
 * The amount stays a decimal string all the way to the JSON. A float would
 * turn 0.1 + 0.2 into 0.30000000000000004 on the way out and undo what the
 * domain's decimal arithmetic guarantees; a string is exact, and every
 * client can parse it. Shared by Catalog and Planning, which may not see
 * each other's application layer.
 */
final readonly class QuantityView
{
    /**
     * @param string $amount positive decimal in its shortest spelling ("150", "0.5")
     * @param string $unit   g, kg, ml, l, pcs or portion
     */
    public function __construct(
        public string $amount,
        public string $unit,
    ) {
    }

    public static function of(Quantity $quantity): self
    {
        return new self($quantity->amount, $quantity->unit->value);
    }
}
