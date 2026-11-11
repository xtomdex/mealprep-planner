<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event\Fixture;

use App\Shared\Domain\Event\AggregateRoot;

/**
 * The smallest aggregate that can record something: it exists only to
 * exercise the trait the way a real aggregate would, through its own method.
 */
final class Basket
{
    use AggregateRoot;

    public function add(string $item, \DateTimeImmutable $now): void
    {
        $this->recordEvent(new ItemAdded($item, $now));
    }
}
