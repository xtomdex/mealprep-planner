<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Application code asks for Psr\Clock\ClockInterface directly; this pins
 * that the container has an implementation for it (Symfony's clock).
 */
final class ClockTest extends KernelTestCase
{
    public function testPsrClockResolvesFromTheContainer(): void
    {
        $clock = self::getContainer()->get(ClockInterface::class);

        self::assertInstanceOf(ClockInterface::class, $clock);
        self::assertInstanceOf(\DateTimeImmutable::class, $clock->now());
    }
}
