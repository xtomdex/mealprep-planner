<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Root of every failure the domain raises on purpose.
 *
 * One common parent lets the outer layers tell "the model refused this
 * request" (catch this type, answer the client) from "the code is broken"
 * (anything else, log it and answer with a server error). The domain itself
 * knows nothing about transports: status codes are chosen where HTTP lives.
 */
abstract class DomainException extends \DomainException
{
}
