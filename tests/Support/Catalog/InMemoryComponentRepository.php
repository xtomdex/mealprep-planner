<?php

declare(strict_types=1);

namespace App\Tests\Support\Catalog;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Repository\ComponentRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Identity\ComponentId;

/**
 * A working ComponentRepository backed by an array; see
 * InMemoryProductRepository for why fakes and not mocks.
 */
final class InMemoryComponentRepository implements ComponentRepository
{
    /** @var array<string, Component> keyed by id value */
    private array $components = [];

    public function get(ComponentId $id): Component
    {
        return $this->components[$id->value] ?? throw new NotFoundException('Component', $id->value);
    }

    public function findByIds(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            if (isset($this->components[$id->value])) {
                $found[$id->value] = $this->components[$id->value];
            }
        }

        return array_values($found);
    }

    public function all(): array
    {
        return array_values($this->components);
    }

    public function add(Component $component): void
    {
        $this->components[$component->id()->value] = $component;
    }

    public function isReferencedAsChild(ComponentId $id): bool
    {
        foreach ($this->components as $component) {
            foreach ($component->ingredients() as $line) {
                if ($line->ref()->isComponent() && $line->ref()->componentId()->equals($id)) {
                    return true;
                }
            }
        }

        return false;
    }
}
