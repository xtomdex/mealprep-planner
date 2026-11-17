<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Marker for the one class that answers a query: a single public method,
 * __invoke(SomeQuery $query), returning the result. Registered by this
 * interface alone, like CommandHandler.
 */
interface QueryHandler
{
}
