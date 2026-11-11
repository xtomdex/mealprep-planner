<?php

declare(strict_types=1);

namespace App\Shared\Domain\Measurement;

/**
 * The closed list of units a recipe or a shopping list may use. The backing
 * value is the symbol shown to people and stored with an amount.
 */
enum Unit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Millilitre = 'ml';
    case Litre = 'l';
    case Piece = 'pcs';
    case Portion = 'portion';

    public function category(): MeasurementCategory
    {
        return match ($this) {
            self::Gram, self::Kilogram => MeasurementCategory::Weight,
            self::Millilitre, self::Litre => MeasurementCategory::Volume,
            self::Piece => MeasurementCategory::Count,
            self::Portion => MeasurementCategory::Portion,
        };
    }

    public function canonical(): self
    {
        return $this->category()->canonicalUnit();
    }

    /**
     * How many canonical units make one of this unit. A string, like every
     * number that takes part in decimal arithmetic, so it never passes
     * through a float.
     */
    public function factorToCanonical(): string
    {
        return match ($this) {
            self::Kilogram, self::Litre => '1000',
            self::Gram, self::Millilitre, self::Piece, self::Portion => '1',
        };
    }
}
