<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\Shared;

use App\Tests\Support\Shared\ImmediateTransactionalRunner;
use PHPUnit\Framework\TestCase;

/**
 * Pins the parts of the TransactionalRunner contract a fake can keep: the
 * work's result comes back, and its exception passes through unchanged.
 */
final class ImmediateTransactionalRunnerTest extends TestCase
{
    public function testReturnsWhatTheWorkReturnedAndCountsTheRun(): void
    {
        $runner = new ImmediateTransactionalRunner();

        self::assertSame(0, $runner->runs());
        self::assertSame(42, $runner->run(static fn (): int => 42));
        self::assertSame(1, $runner->runs());
    }

    public function testRethrowsTheWorksExceptionUnchanged(): void
    {
        $runner = new ImmediateTransactionalRunner();
        $failure = new \RuntimeException('boom');

        $caught = null;
        try {
            $runner->run(static fn (): int => throw $failure);
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        self::assertSame($failure, $caught);
        self::assertSame(1, $runner->runs());
    }
}
