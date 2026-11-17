<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Query;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * QueryBus over a synchronous Messenger bus; the answer is the result the
 * handler left in its HandledStamp.
 */
final readonly class MessengerQueryBus implements QueryBus
{
    public function __construct(
        #[Autowire(service: 'query.bus')]
        private MessageBusInterface $bus,
    ) {
    }

    public function ask(Query $query): mixed
    {
        try {
            $envelope = $this->bus->dispatch($query);
        } catch (HandlerFailedException $e) {
            // Unwrapped for the same reason as in MessengerCommandBus.
            throw $e->getPrevious() ?? $e;
        }

        // No handler at all already fails inside Messenger. Two handlers
        // would both run and leave two answers; picking one would hide a
        // wiring mistake, so that is refused too.
        $handled = $envelope->all(HandledStamp::class);
        if (1 !== \count($handled)) {
            throw new \LogicException(\sprintf('Query %s must have exactly one handler, %d handled it.', $query::class, \count($handled)));
        }

        return $handled[0]->getResult();
    }
}
