<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Something was addressed by identifier and does not exist.
 *
 * Resource and id are kept apart from the message so that the layer building
 * a response can render them itself instead of parsing text.
 */
final class NotFoundException extends DomainException
{
    public function __construct(
        private readonly string $resource,
        private readonly string $id,
    ) {
        parent::__construct(\sprintf('%s "%s" was not found.', $resource, $id));
    }

    public function resource(): string
    {
        return $this->resource;
    }

    public function id(): string
    {
        return $this->id;
    }
}
