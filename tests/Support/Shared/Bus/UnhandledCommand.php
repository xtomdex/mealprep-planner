<?php

declare(strict_types=1);

namespace App\Tests\Support\Shared\Bus;

use App\Shared\Application\Bus\Command;

/**
 * Deliberately has no handler.
 */
final readonly class UnhandledCommand implements Command
{
}
