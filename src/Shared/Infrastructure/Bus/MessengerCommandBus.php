<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Command;
use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * CommandBus over a synchronous Messenger bus. A command without a handler
 * fails with Messenger's NoHandlerForMessageException: a forgotten handler
 * is a bug and must not pass silently.
 */
final readonly class MessengerCommandBus implements CommandBus
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $bus,
    ) {
    }

    public function dispatch(Command $command): void
    {
        try {
            $this->bus->dispatch($command);
        } catch (HandlerFailedException $e) {
            // Messenger wraps whatever the handler threw. Unwrapping keeps a
            // NotFoundException a NotFoundException for the caller (and for
            // the HTTP error mapping); the previous exception is the first
            // failure, and a command has exactly one handler.
            throw $e->getPrevious() ?? $e;
        }
    }
}
