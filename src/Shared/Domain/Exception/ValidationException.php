<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * A business rule (invariant) was violated by the given input.
 *
 * The code is a short stable identifier owned by the module that defines the
 * rule, for example "QTY-01". Clients and tests match on it rather than on
 * the message, so the wording can change without breaking anyone.
 */
final class ValidationException extends DomainException
{
    // PHP's built-in exception code is an int behind a final getCode(), so the
    // invariant code needs its own property and accessor.
    private readonly string $errorCode;

    public function __construct(
        string $code,
        string $message,
        private readonly ?string $field = null,
    ) {
        parent::__construct($message);

        $this->errorCode = $code;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Name of the input field the rule is about, when there is a single one;
     * null for rules that span several fields or the aggregate as a whole.
     */
    public function field(): ?string
    {
        return $this->field;
    }
}
