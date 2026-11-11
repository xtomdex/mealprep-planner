<?php

declare(strict_types=1);

namespace App\Shared\Domain\Decimal;

/**
 * Decimal arithmetic for quantities (grams, portions, yields).
 *
 * Values travel as strings and are computed with BCMath, because a float
 * cannot represent most decimal fractions exactly: summing a shopping list
 * in floats ends in figures like 0.30000000000000004. Every result carries
 * exactly SCALE fractional digits; digits beyond that are cut off, not
 * rounded, which is how BCMath behaves and keeps results predictable.
 */
final class Decimal
{
    /**
     * Six digits is a microgram when the unit is a gram: fine enough that
     * chained multiplications and divisions do not lose visible precision.
     */
    public const int SCALE = 6;

    /**
     * Stricter than is_numeric() on purpose: no exponent ("1e3"), no leading
     * sign "+", no surrounding spaces, no bare ".5". One accepted spelling
     * means a value that passed here can be stored and compared as is.
     */
    private const string PATTERN = '/\A-?\d+(\.\d+)?\z/';

    private function __construct()
    {
    }

    public static function add(string $a, string $b): string
    {
        return bcadd(self::numeric($a), self::numeric($b), self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::numeric($a), self::numeric($b), self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul(self::numeric($a), self::numeric($b), self::SCALE);
    }

    /**
     * A zero divisor is left to PHP's own DivisionByZeroError instead of a
     * domain exception: it is a bug in the caller (a yield or a portion count
     * must be validated as positive before any maths), not a business rule
     * violation that a user could trigger and fix.
     *
     * @throws \DivisionByZeroError
     */
    public static function div(string $a, string $b): string
    {
        return bcdiv(self::numeric($a), self::numeric($b), self::SCALE);
    }

    /**
     * Compares at the working scale, so two values that differ only beyond
     * the sixth fractional digit are equal — consistent with the fact that
     * no operation here can produce such a difference.
     *
     * @return int -1, 0 or 1, like the spaceship operator
     */
    public static function cmp(string $a, string $b): int
    {
        return bccomp(self::numeric($a), self::numeric($b), self::SCALE);
    }

    /**
     * Rounds up to the nearest whole number: 2.1 packs to buy means 3 packs.
     * The result stays a string so it can be fed straight back into the other
     * operations and never overflows an int.
     */
    public static function ceil(string $value): string
    {
        return bcceil(self::numeric($value));
    }

    /**
     * Shortest equivalent spelling for output and storage: "1.500000" → "1.5",
     * "2.000000" → "2". Whole numbers keep their zeros ("100" stays "100") and
     * a negative zero loses its sign.
     */
    public static function normalize(string $value): string
    {
        $value = self::numeric($value);

        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return 1 === preg_match('/\A-0+\z/', $value) ? '0' : $value;
    }

    /**
     * BCMath would reject malformed input too, but with a ValueError that
     * names its own argument position; failing here names the offending value.
     *
     * @return numeric-string
     */
    private static function numeric(string $value): string
    {
        // is_numeric() adds nothing to the pattern at runtime; it is what lets
        // static analysis see the value as a numeric-string.
        if (1 !== preg_match(self::PATTERN, $value) || !is_numeric($value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a decimal number.', $value));
        }

        return $value;
    }
}
