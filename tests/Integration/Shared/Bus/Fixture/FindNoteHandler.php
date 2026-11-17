<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Bus\Fixture;

use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Domain\Exception\NotFoundException;

/**
 * Knows one note; any other id is a NotFoundException, which lets the test
 * check that domain exceptions cross the bus unwrapped.
 */
final class FindNoteHandler implements QueryHandler
{
    public const string KNOWN = 'n-1';

    public function __invoke(FindNote $query): string
    {
        return self::KNOWN === $query->id ? 'Buy rice' : throw new NotFoundException('Note', $query->id);
    }
}
