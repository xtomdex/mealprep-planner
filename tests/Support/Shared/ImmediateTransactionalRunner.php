<?php

declare(strict_types=1);

namespace App\Tests\Support\Shared;

use App\Shared\Application\TransactionalRunner;

/**
 * Runs the work straight away, with no transaction: unit tests use
 * in-memory repositories, which have nothing to commit. It counts the runs,
 * so a handler test can check that its writes went through the runner.
 */
final class ImmediateTransactionalRunner implements TransactionalRunner
{
    private int $runs = 0;

    public function run(callable $work): mixed
    {
        ++$this->runs;

        return $work();
    }

    public function runs(): int
    {
        return $this->runs;
    }
}
