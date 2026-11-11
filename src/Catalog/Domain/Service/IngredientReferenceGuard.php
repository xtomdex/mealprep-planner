<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Model\Composition;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ComponentRepository;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Measurement\Unit;

/**
 * Checks recipe lines against the aggregates they point at. An aggregate
 * cannot do this itself: it sees only its own state, and "does product X
 * exist and how is it measured" is someone else's. So the check lives in a
 * domain service that reads through the repository ports, and a use case
 * calls it before creating a component or a dish.
 *
 * Referenced aggregates are loaded with findByIds(), one query per kind,
 * rather than with get() per line: a recipe of fifteen lines then costs two
 * queries, not fifteen, and a missing id is simply absent from the result
 * instead of an exception to translate.
 *
 * Every error names the offending line by position and reference, so an API
 * client can point at the exact line to fix. The first offending line wins.
 */
final readonly class IngredientReferenceGuard
{
    public function __construct(
        private ProductRepository $products,
        private ComponentRepository $components,
    ) {
    }

    /**
     * ING-01: every referenced product and component exists.
     * ING-02: a product line is measured in the product's measurement category.
     * ING-03: a component line is measured in portions.
     *
     * @param list<IngredientLine> $lines
     *
     * @throws ValidationException ING-01, ING-02, ING-03
     */
    public function assertValidForDish(array $lines): void
    {
        $this->assertLines($lines, null);
    }

    /**
     * ING-01..03 as for a dish, plus ING-04: a component's bill of materials
     * nests at most two levels deep and has no cycles. That holds when the
     * child is not the component itself, the child is flat (has no component
     * lines), and the component itself is not already someone's child.
     *
     * @param list<IngredientLine> $lines
     *
     * @throws ValidationException ING-01, ING-02, ING-03, ING-04
     */
    public function assertValidForComponent(ComponentId $self, array $lines): void
    {
        $this->assertLines($lines, $self);

        $usesComponents = array_any($lines, static fn (IngredientLine $line): bool => $line->ref->isComponent());
        if ($usesComponents && $this->components->isReferencedAsChild($self)) {
            throw new ValidationException('ING-04', \sprintf('Component %s is used inside another component, so it cannot contain components itself (at most two levels).', $self), 'ingredients');
        }
    }

    /**
     * @param list<IngredientLine> $lines
     * @param ComponentId|null     $self  the component being validated, null for a dish
     */
    private function assertLines(array $lines, ?ComponentId $self): void
    {
        $products = $this->loadProducts($lines);
        $components = $this->loadComponents($lines);

        foreach ($lines as $i => $line) {
            $label = \sprintf('Line %d (%s)', $i + 1, Composition::describe($line->ref));

            if ($line->ref->isProduct()) {
                $product = $products[$line->ref->productId()->value]
                    ?? throw new ValidationException('ING-01', $label.': the product does not exist.', 'ingredients');

                if ($line->quantity->unit->category() !== $product->measurementCategory()) {
                    throw new ValidationException('ING-02', \sprintf('%s: "%s" is measured by %s, %s is not.', $label, $product->name(), $product->measurementCategory()->value, $line->quantity), 'ingredients');
                }

                continue;
            }

            $childId = $line->ref->componentId();

            // Before the existence check: on create the component is not
            // stored yet, and "does not exist" would hide the real problem.
            if (null !== $self && $childId->equals($self)) {
                throw new ValidationException('ING-04', $label.': a component cannot contain itself.', 'ingredients');
            }

            $child = $components[$childId->value]
                ?? throw new ValidationException('ING-01', $label.': the component does not exist.', 'ingredients');

            if (Unit::Portion !== $line->quantity->unit) {
                throw new ValidationException('ING-03', \sprintf('%s: a component is measured in portions, got %s.', $label, $line->quantity), 'ingredients');
            }

            if (null !== $self && $child->hasComponentLines()) {
                throw new ValidationException('ING-04', \sprintf('%s: "%s" contains components itself; only a flat component can be used inside another (at most two levels).', $label, $child->name()), 'ingredients');
            }
        }
    }

    /**
     * @param list<IngredientLine> $lines
     *
     * @return array<string, Product> keyed by id value
     */
    private function loadProducts(array $lines): array
    {
        $ids = [];
        foreach ($lines as $line) {
            if ($line->ref->isProduct()) {
                $ids[] = $line->ref->productId();
            }
        }

        $byId = [];
        foreach ([] === $ids ? [] : $this->products->findByIds($ids) as $product) {
            $byId[$product->id()->value] = $product;
        }

        return $byId;
    }

    /**
     * @param list<IngredientLine> $lines
     *
     * @return array<string, Component> keyed by id value
     */
    private function loadComponents(array $lines): array
    {
        $ids = [];
        foreach ($lines as $line) {
            if ($line->ref->isComponent()) {
                $ids[] = $line->ref->componentId();
            }
        }

        $byId = [];
        foreach ([] === $ids ? [] : $this->components->findByIds($ids) as $component) {
            $byId[$component->id()->value] = $component;
        }

        return $byId;
    }
}
