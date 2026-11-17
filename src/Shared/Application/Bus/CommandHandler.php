<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Marker for the one class that executes a command. The handler has a single
 * public method, __invoke(SomeCommand $command): void; the type of its
 * argument is how the bus finds it.
 *
 * Implementing this interface is all a handler does to be registered (see
 * services.yaml), so handlers stay plain classes without framework
 * attributes and can be created with `new` in a unit test.
 */
interface CommandHandler
{
}
