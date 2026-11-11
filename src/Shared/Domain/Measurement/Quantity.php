<?php

declare(strict_types=1);

namespace App\Shared\Domain\Measurement;

use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Exception\ValidationException;

/**
 * An amount together with its unit: "250 g", "1.5 kg", "2 portion".
 *
 * A bare number loses its meaning the moment it leaves the line it was typed
 * on; keeping the unit attached makes "add grams to millilitres" an error at
 * the point where it happens instead of a wrong shopping list later.
 *
 * Immutable: every operation returns a new Quantity. "Canonical" means
 * expressed in the base unit of the category (g, ml, pcs, portion).
 */
final readonly class Quantity implements \Stringable
{
    /**
     * @param string $amount always positive, in the shortest spelling, with at most Decimal::SCALE fractional digits
     */
    private function __construct(
        public string $amount,
        public Unit $unit,
    ) {
    }

    /**
     * QTY-01: an amount must be greater than zero. A recipe line with nothing
     * in it, or a negative one, is always a mistake, so no Quantity can exist
     * in that state and the rest of the model never has to check for it.
     *
     * A malformed amount string ("abc", "1,5") is a different matter: that is
     * a bug in the calling code, and Decimal's InvalidArgumentException is
     * left to surface as one.
     *
     * @throws ValidationException QTY-01
     */
    public static function of(string $amount, Unit $unit): self
    {
        if (Decimal::cmp($amount, '0') <= 0) {
            throw new ValidationException('QTY-01', \sprintf('Quantity must be greater than zero, got %s %s', $amount, $unit->value), 'amount');
        }

        // Adding zero brings the amount to the working scale first, so digits
        // beyond it are dropped and equal amounts always share one spelling.
        return new self(Decimal::normalize(Decimal::add($amount, '0')), $unit);
    }

    public function toCanonical(): self
    {
        return self::of(Decimal::mul($this->amount, $this->unit->factorToCanonical()), $this->unit->canonical());
    }

    /**
     * QTY-02: only quantities of the same category can be added. Weight to
     * volume would need a density, pieces to grams a weight per piece; the
     * model has neither, so it refuses instead of guessing.
     *
     * The sum is always canonical (1 kg + 250 g → 1250 g): totals built from
     * mixed units then come out in one unit regardless of the order of the
     * operands.
     *
     * @throws ValidationException QTY-02
     */
    public function add(self $other): self
    {
        if ($this->unit->category() !== $other->unit->category()) {
            throw new ValidationException('QTY-02', \sprintf('Cannot add %s and %s: incompatible units', $this, $other));
        }

        return self::of(Decimal::add($this->toCanonical()->amount, $other->toCanonical()->amount), $this->unit->canonical());
    }

    /**
     * Scales the amount, e.g. by a number of portions; the unit stays as it is.
     *
     * QTY-01 applies to the result: a zero or negative factor, or one so small
     * that the product vanishes at the working scale, is rejected.
     *
     * @param string $factor a decimal string, like the amount itself
     *
     * @throws ValidationException QTY-01
     */
    public function multiply(string $factor): self
    {
        return self::of(Decimal::mul($this->amount, $factor), $this->unit);
    }

    /**
     * Rounds up to a whole number of the current unit (333.33 g → 334 g).
     * Meant for the shopping list: nobody buys a third of a gram or half an
     * egg, and rounding down would leave the cook short.
     *
     * The unit is deliberately not converted: 1.2 kg becomes 2 kg. Convert
     * with toCanonical() first when gram precision is wanted.
     */
    public function roundUpToWhole(): self
    {
        return self::of(Decimal::ceil($this->amount), $this->unit);
    }

    /**
     * Compares what is measured, not how it is written: 1 kg equals 1000 g.
     */
    public function equals(self $other): bool
    {
        $a = $this->toCanonical();
        $b = $other->toCanonical();

        return $a->unit === $b->unit && 0 === Decimal::cmp($a->amount, $b->amount);
    }

    public function __toString(): string
    {
        return $this->amount.' '.$this->unit->value;
    }
}
