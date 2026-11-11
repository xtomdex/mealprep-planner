<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;

/**
 * A pointer from a recipe line to what it uses: either a product or another
 * component, never both and never neither.
 *
 * Two nullable ids with a public constructor would allow four combinations,
 * two of them meaningless. Here the constructor is private and each named
 * constructor fills exactly one id, so an invalid reference cannot be built
 * and nothing downstream has to validate it.
 */
final readonly class IngredientRef
{
    private function __construct(
        private RefKind $kind,
        private ?ProductId $productId,
        private ?ComponentId $componentId,
    ) {
    }

    public static function product(ProductId $id): self
    {
        return new self(RefKind::Product, $id, null);
    }

    public static function component(ComponentId $id): self
    {
        return new self(RefKind::Component, null, $id);
    }

    public function kind(): RefKind
    {
        return $this->kind;
    }

    public function isProduct(): bool
    {
        return RefKind::Product === $this->kind;
    }

    public function isComponent(): bool
    {
        return RefKind::Component === $this->kind;
    }

    /**
     * Throws instead of returning null: asking a component reference for a
     * product id means the caller skipped the kind check, and a null would
     * carry that bug further away from its cause.
     *
     * @throws \LogicException when this reference points at a component
     */
    public function productId(): ProductId
    {
        return $this->productId ?? throw new \LogicException('This ingredient refers to a component, not a product; check isProduct() first.');
    }

    /**
     * @throws \LogicException when this reference points at a product
     */
    public function componentId(): ComponentId
    {
        return $this->componentId ?? throw new \LogicException('This ingredient refers to a product, not a component; check isComponent() first.');
    }

    public function equals(self $other): bool
    {
        if ($this->kind !== $other->kind) {
            return false;
        }

        // Same kind on both sides, so the same accessor is safe for both.
        return $this->isProduct()
            ? $this->productId()->equals($other->productId())
            : $this->componentId()->equals($other->componentId());
    }
}
