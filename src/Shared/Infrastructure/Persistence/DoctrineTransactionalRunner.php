<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\TransactionalRunner;
use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\Event\DomainEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * TransactionalRunner over Doctrine: the work and the flush run in one
 * database transaction, so the work's changes and the SQL they produce
 * commit together or not at all. Repositories only persist; this is the one
 * place that flushes.
 *
 * Doctrine closes the EntityManager after an exception inside a transaction,
 * because its unit of work may be half-applied. A closed manager refuses
 * every later call, so the next run() in the same process (the next test,
 * the next message in a worker) resets it here first. This is the right
 * place because every write passes through it; the registry resets the lazy
 * service in place, so repositories that captured the manager keep working.
 * Symfony's kernel.reset does the same between worker messages, which makes
 * this a second line of defence there, and the only one inside a single
 * process that never resets the kernel.
 *
 * Domain events go out after the commit, never before: a subscriber must
 * not react to a change that is then rolled back. The aggregates are taken
 * from the identity map before the flush (everything the unit of work
 * holds, loaded or newly persisted), and their events are released only
 * once the commit succeeded. On a rollback the events are released and
 * dropped, so a later save of the same object cannot announce a change
 * that never happened.
 *
 * No outbox yet: every subscriber is internal and synchronous, so "after
 * the commit, in the same process" is all the guarantee they need. An
 * outbox (events stored in the same transaction, sent by a relay) comes
 * with the first subscriber outside this process, which must not miss an
 * event when the process dies between commit and dispatch.
 *
 * A subscriber that throws is logged and skipped, and the other events are
 * still dispatched. The write is already durable at that point; rethrowing
 * would answer the client with a 500 for an action that succeeded.
 *
 * Messenger is used directly rather than behind an application port: only
 * this class publishes events, so a port would have one caller and one
 * implementation, both in infrastructure.
 */
final readonly class DoctrineTransactionalRunner implements TransactionalRunner
{
    public function __construct(
        private ManagerRegistry $doctrine,
        #[Autowire(service: 'event.bus')]
        private MessageBusInterface $events,
        private LoggerInterface $logger,
    ) {
    }

    public function run(callable $work): mixed
    {
        $manager = $this->openManager();
        $connection = $manager->getConnection();

        $aggregates = null;
        $connection->beginTransaction();
        try {
            $result = $work();
            $aggregates = self::aggregates($manager);
            $manager->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            // A failed flush has already closed the manager, and closing
            // clears the identity map; the list taken before the flush is
            // then the only record of what was in it.
            foreach ($aggregates ?? self::aggregates($manager) as $aggregate) {
                self::release($aggregate);
            }
            $manager->close();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $e;
        }

        foreach ($aggregates as $aggregate) {
            foreach (self::release($aggregate) as $event) {
                $this->publish($event);
            }
        }

        return $result;
    }

    private function publish(DomainEvent $event): void
    {
        try {
            $this->events->dispatch($event);
        } catch (\Throwable $e) {
            $this->logger->error('A subscriber failed on {event} after the commit; the change itself is stored.', [
                'event' => $event::class,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Uninitialized lazy objects are skipped: they were never loaded, so they
     * cannot have recorded anything, and touching one would load it.
     *
     * @return list<object>
     */
    private static function aggregates(EntityManagerInterface $manager): array
    {
        $unitOfWork = $manager->getUnitOfWork();
        $aggregates = [];
        foreach ($unitOfWork->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if (!$unitOfWork->isUninitializedObject($entity) && \in_array(AggregateRoot::class, class_uses($entity), true)) {
                    $aggregates[] = $entity;
                }
            }
        }

        return $aggregates;
    }

    /**
     * AggregateRoot is a trait, so there is no interface to type against;
     * the trait check above picks the objects, this one satisfies the types.
     *
     * @return list<DomainEvent>
     */
    private static function release(object $aggregate): array
    {
        if (!method_exists($aggregate, 'releaseEvents')) {
            return [];
        }

        $released = $aggregate->releaseEvents();
        if (!\is_array($released)) {
            return [];
        }

        $events = [];
        foreach ($released as $event) {
            if ($event instanceof DomainEvent) {
                $events[] = $event;
            }
        }

        return $events;
    }

    private function openManager(): EntityManagerInterface
    {
        $manager = $this->doctrine->getManager();
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(\sprintf('The default manager must be an ORM EntityManager, got %s.', get_debug_type($manager)));
        }

        if (!$manager->isOpen()) {
            $this->doctrine->resetManager();

            return $this->openManager();
        }

        return $manager;
    }
}
