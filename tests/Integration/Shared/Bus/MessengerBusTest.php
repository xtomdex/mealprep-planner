<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Bus;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Integration\Shared\Bus\Fixture\FindNote;
use App\Tests\Integration\Shared\Bus\Fixture\FindNoteHandler;
use App\Tests\Integration\Shared\Bus\Fixture\RecordNote;
use App\Tests\Integration\Shared\Bus\Fixture\RecordNoteHandler;
use App\Tests\Integration\Shared\Bus\Fixture\UnhandledCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

/**
 * Proves the wiring, not Messenger: the ports resolve to the Messenger
 * buses, handlers are found through the marker interfaces alone, and
 * exceptions come out the way the Application layer expects them.
 */
final class MessengerBusTest extends KernelTestCase
{
    public function testACommandReachesItsHandler(): void
    {
        $this->service(CommandBus::class)->dispatch(new RecordNote('Buy rice'));

        self::assertSame(['Buy rice'], $this->service(RecordNoteHandler::class)->notes);
    }

    public function testAQueryReturnsItsHandlersResult(): void
    {
        self::assertSame('Buy rice', $this->service(QueryBus::class)->ask(new FindNote(FindNoteHandler::KNOWN)));
    }

    public function testADomainExceptionFromAHandlerArrivesUnwrapped(): void
    {
        try {
            $this->service(QueryBus::class)->ask(new FindNote('n-404'));
            self::fail('Expected NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame(['Note', 'n-404'], [$e->resource(), $e->id()]);
        }
    }

    public function testACommandWithoutAHandlerFailsLoudly(): void
    {
        $this->expectException(NoHandlerForMessageException::class);

        $this->service(CommandBus::class)->dispatch(new UnhandledCommand());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
