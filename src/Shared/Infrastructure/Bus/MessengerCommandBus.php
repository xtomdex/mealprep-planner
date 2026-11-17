<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Command;
use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * CommandBus over a synchronous Messenger bus. A command without a handler
 * fails with Messenger's NoHandlerForMessageException, one with two handlers
 * with a LogicException: either way a wiring bug must not pass silently.
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
            $envelope = $this->bus->dispatch($command);
        } catch (HandlerFailedException $e) {
            // Messenger wraps whatever the handler threw. Unwrapping keeps a
            // NotFoundException a NotFoundException for the caller (and for
            // the HTTP error mapping); the previous exception is the first
            // failure, and a command has exactly one handler.
            throw $e->getPrevious() ?? $e;
        }

        // Messenger runs every handler it finds, so a second handler is
        // only noticed afterwards. It is still a wiring bug, and failing on
        // the first dispatch (in a test) beats a write done twice in silence.
        $handled = $envelope->all(HandledStamp::class);
        if (\count($handled) > 1) {
            throw new \LogicException(\sprintf('Command %s must have exactly one handler, %d handled it.', $command::class, \count($handled)));
        }
    }
}
