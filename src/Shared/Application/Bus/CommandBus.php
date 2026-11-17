<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Sends a command to its handler.
 *
 * Commands return nothing (command–query separation): an id a caller needs
 * afterwards is generated before dispatch and travels inside the command,
 * and anything else is asked for with a query. Writes then have one shape,
 * and reading a result never depends on how it was written.
 *
 * This is our own port rather than Messenger's MessageBusInterface: the
 * Application layer stays free of Symfony, callers deal with a typed
 * Command instead of an Envelope, and a handler can be tested by calling it
 * directly, without a container or a bus.
 */
interface CommandBus
{
    /**
     * Synchronous: when this returns, the handler has run. An exception
     * thrown by the handler reaches the caller as itself, not wrapped.
     */
    public function dispatch(Command $command): void;
}
