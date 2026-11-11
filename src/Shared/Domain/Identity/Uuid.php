<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identity;

/**
 * Base for typed identifiers: each aggregate gets its own subclass, so that
 * a product id cannot be passed where a dish id is expected — a mistake that
 * plain strings let through silently.
 *
 * The format is checked with a regex instead of a UUID library to keep the
 * domain free of framework imports. Only the shape is checked, not the
 * version: the domain does not care how an id was generated.
 */
abstract readonly class Uuid implements \Stringable
{
    /**
     * Lowercase only. Uppercase spellings are rejected rather than converted,
     * so one id has exactly one string form and comparing strings (in PHP,
     * in SQL, in logs) is the same as comparing ids.
     */
    private const string PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/';

    // Final so that no subclass can skip the format check.
    final public function __construct(public string $value)
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a canonical UUID.', $value));
        }
    }

    /**
     * Ids of different types are never equal, even with the same value:
     * they point at different things.
     */
    public function equals(self $other): bool
    {
        return $other::class === static::class && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
