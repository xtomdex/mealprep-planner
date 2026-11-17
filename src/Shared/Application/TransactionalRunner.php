<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * Runs a unit of work in one transaction: everything inside commits, or
 * nothing does. If the work throws, the transaction is rolled back and the
 * exception is rethrown unchanged.
 *
 * Handlers call this explicitly instead of relying on a Messenger
 * middleware that wraps every command. The boundary is then visible in the
 * code where it applies: a reader of the handler sees what is atomic, and a
 * handler that must do something after the commit (or needs no transaction)
 * says so in the same place, not in bus configuration.
 */
interface TransactionalRunner
{
    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T whatever the work returned, after the commit
     */
    public function run(callable $work): mixed;
}
