<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Identity;

use App\Shared\Domain\Identity\IdGenerator;
use Symfony\Component\Uid\Uuid;

/**
 * Version 7 rather than the random version 4: the leading bits are a
 * timestamp, so new ids are appended at the end of a primary-key index
 * instead of landing on random pages. That keeps inserts cheap and makes
 * "order by id" follow creation order.
 */
final class SymfonyUuidGenerator implements IdGenerator
{
    public function nextId(): string
    {
        // RFC 4122 text form is the lowercase 8-4-4-4-12 spelling the domain expects.
        return Uuid::v7()->toRfc4122();
    }
}
