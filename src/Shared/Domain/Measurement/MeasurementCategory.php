<?php

declare(strict_types=1);

namespace App\Shared\Domain\Measurement;

/**
 * What kind of thing is being measured. Amounts can be converted and summed
 * inside one category only: grams never turn into millilitres here, because
 * that needs a density the model does not have.
 */
enum MeasurementCategory: string
{
    case Weight = 'weight';
    case Volume = 'volume';
    case Count = 'count';
    case Portion = 'portion';

    /**
     * The unit every amount of this category is reduced to before it is
     * compared or summed. The smaller unit is chosen (g, not kg) so that
     * everyday amounts stay whole numbers.
     */
    public function canonicalUnit(): Unit
    {
        return match ($this) {
            self::Weight => Unit::Gram,
            self::Volume => Unit::Millilitre,
            self::Count => Unit::Piece,
            self::Portion => Unit::Portion,
        };
    }
}
