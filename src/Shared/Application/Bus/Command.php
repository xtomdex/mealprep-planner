<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Marker for a request to change state: "add this entry", "finalize that
 * plan". A command is a plain readonly object that carries everything its
 * handler needs, including the ids of anything it creates.
 */
interface Command
{
}
