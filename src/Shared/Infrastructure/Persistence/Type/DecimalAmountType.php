<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Type;

use App\Shared\Domain\Decimal\Decimal;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;

/**
 * A decimal amount (numeric(14,6) in the mapping) read back in the shortest
 * spelling: "150.000000" becomes "150", "0.500000" becomes "0.5".
 *
 * PostgreSQL pads a numeric to its declared scale, and Doctrine hydrates an
 * embeddable without calling its factory, so Quantity::of() never gets the
 * chance to normalize the amount. The domain relies on one spelling per
 * value: amounts are compared and grouped as strings, and they go out to the
 * API as they are. "150.000000" next to "150" would be two different amounts
 * in a group-by and a visible difference in a response. Normalizing here,
 * with the same Decimal helper Quantity uses, gives a loaded Quantity the
 * spelling it had when it was created.
 *
 * Writing needs nothing: a Quantity already holds the normalized string.
 */
final class DecimalAmountType extends DecimalType
{
    public const string NAME = 'decimal_amount';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        $value = parent::convertToPHPValue($value, $platform);

        if (null === $value) {
            return null;
        }

        try {
            return Decimal::normalize($value);
        } catch (\InvalidArgumentException $e) {
            throw ValueNotConvertible::new($value, self::class, null, $e);
        }
    }
}
