<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * The request is well-formed but clashes with the current state: a duplicate
 * name, a change to something already finalised.
 *
 * Kept apart from ValidationException because the remedy differs: invalid
 * input has to be corrected, a conflict may simply go away once the state
 * changes. The code works the same way as there.
 */
final class ConflictException extends DomainException
{
    // See ValidationException: the built-in exception code cannot hold a string.
    private readonly string $errorCode;

    public function __construct(string $code, string $message)
    {
        parent::__construct($message);

        $this->errorCode = $code;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
