<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Marker for the one class that answers a query: a single public method,
 * __invoke(SomeQuery $query), returning the result. Registered by this
 * interface alone, like CommandHandler.
 *
 * CQRS-lite: queries and commands are separate messages on separate buses,
 * but both sides use the same storage. A handler reads through the domain
 * repository ports (all(), get(), findByIds(), findByPlan()) instead of a
 * dedicated read model or hand-written SQL; that lives in Reporting, where
 * the shape of the answer has nothing in common with an aggregate. A second
 * model for screens this simple would be two places to keep in step for no
 * gain in speed.
 *
 * What a handler returns is a view, never an aggregate or a domain value
 * object: plain readonly classes with ids as strings, days as 'Y-m-d'
 * strings, moments as \DateTimeImmutable and quantities as a QuantityView
 * whose amount is a decimal string. The HTTP layer (D-07) serializes views
 * as they are, so the JSON contract is written down in one class per
 * resource, and renaming a method of an aggregate cannot change an API
 * response or expose a setter-like method to a caller.
 *
 * Lists come back whole, without pagination: the demo catalog and plan
 * history stay small, and paging (with its total counts and stable cursors)
 * arrives with the first list that can grow past a screen.
 */
interface QueryHandler
{
}
