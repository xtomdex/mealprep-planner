<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\Component;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\ComponentId;

interface ComponentRepository
{
    /**
     * @throws NotFoundException when there is no component with this id
     */
    public function get(ComponentId $id): Component;

    /**
     * Same contract as ProductRepository::findByIds(): unknown ids are
     * skipped, the order is not defined.
     *
     * @param list<ComponentId> $ids
     *
     * @return list<Component>
     */
    public function findByIds(array $ids): array;

    /**
     * @return list<Component>
     */
    public function all(): array;

    public function add(Component $component): void;

    /**
     * Whether some other component has a line pointing at this one. Such a
     * component is a child and must stay flat, or nesting would grow past
     * two levels.
     */
    public function isReferencedAsChild(ComponentId $id): bool;
}
