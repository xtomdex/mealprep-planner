<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identity;

/**
 * Port for producing new identifiers.
 *
 * Ids are generated in the application before anything is saved, so an
 * aggregate is complete from the moment it is created and a handler can
 * return the id without asking the database. The domain declares the need;
 * the algorithm is an infrastructure choice, and tests can supply fixed ids.
 */
interface IdGenerator
{
    /**
     * @return string a canonical lowercase UUID, ready to be wrapped in a typed id
     */
    public function nextId(): string;
}
