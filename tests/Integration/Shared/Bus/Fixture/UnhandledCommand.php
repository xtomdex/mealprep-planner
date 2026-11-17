<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Bus\Fixture;

use App\Shared\Application\Bus\Command;

/**
 * Deliberately has no handler.
 */
final readonly class UnhandledCommand implements Command
{
}
