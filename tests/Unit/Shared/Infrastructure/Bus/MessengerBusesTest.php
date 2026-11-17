<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus;

use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Infrastructure\Bus\MessengerCommandBus;
use App\Shared\Infrastructure\Bus\MessengerQueryBus;
use App\Tests\Support\Shared\Bus\FindNote;
use App\Tests\Support\Shared\Bus\RecordNote;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

/**
 * Edge cases that need an unusual handler setup, on a real Messenger bus
 * built by hand instead of the container: a failing command handler and a
 * query that two handlers answer.
 */
final class MessengerBusesTest extends TestCase
{
    public function testACommandHandlersExceptionArrivesUnwrapped(): void
    {
        $conflict = new ConflictException('TST-01', 'Already done.');
        $bus = new MessengerCommandBus(self::bus([RecordNote::class => [static fn (RecordNote $c) => throw $conflict]]));

        try {
            $bus->dispatch(new RecordNote('x'));
            self::fail('Expected ConflictException.');
        } catch (ConflictException $e) {
            self::assertSame($conflict, $e);
        }
    }

    public function testAQueryAnsweredByTwoHandlersIsAProgrammingError(): void
    {
        // Two distinct classes: Messenger tells handlers apart by name, and
        // two closures would share the name "Closure" and run only once.
        $first = new class {
            public function __invoke(FindNote $query): string
            {
                return 'a';
            }
        };
        $second = new class {
            public function __invoke(FindNote $query): string
            {
                return 'b';
            }
        };
        $bus = new MessengerQueryBus(self::bus([FindNote::class => [$first, $second]]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('Query %s must have exactly one handler, 2 handled it.', FindNote::class));

        $bus->ask(new FindNote('n-1'));
    }

    /**
     * @param array<class-string, list<callable>> $handlers
     */
    private static function bus(array $handlers): MessageBus
    {
        return new MessageBus([new HandleMessageMiddleware(new HandlersLocator($handlers))]);
    }
}
