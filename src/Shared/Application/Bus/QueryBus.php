<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Sends a query to its handler and returns the handler's result.
 *
 * Our own port for the same reasons as CommandBus. The result is mixed
 * because each query defines its own; the caller knows which query it asked.
 */
interface QueryBus
{
    /**
     * Synchronous; an exception thrown by the handler reaches the caller as
     * itself, not wrapped.
     */
    public function ask(Query $query): mixed;
}
