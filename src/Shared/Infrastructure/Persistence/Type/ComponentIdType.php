<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Type;

use App\Shared\Domain\Identity\ComponentId;

/**
 * @extends UuidType<ComponentId>
 */
final class ComponentIdType extends UuidType
{
    public const string NAME = 'component_id';

    protected function idClass(): string
    {
        return ComponentId::class;
    }
}
