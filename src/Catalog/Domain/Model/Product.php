<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;

/**
 * Something that is bought: chicken breast, rice, olive oil.
 *
 * The measurement category says how the product is counted (rice by weight,
 * oil by volume, eggs by the piece); every recipe line that uses the product
 * has to be measured in a unit of that category, otherwise the shopping list
 * could not sum the lines up.
 *
 * PRD-02 (product names are unique) is deliberately not checked here: an
 * aggregate cannot see its siblings, and a check-then-insert in the
 * application would race. It is a UNIQUE constraint in the database, which
 * the infrastructure layer turns into a 409 Conflict.
 */
final class Product
{
    private function __construct(
        private readonly ProductId $id,
        private readonly string $name,
        private readonly MeasurementCategory $measurementCategory,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * PRD-01: the name is non-empty after trimming and at most 120 characters;
     * the trimmed name is stored.
     *
     * @throws ValidationException PRD-01
     */
    public static function create(ProductId $id, string $name, MeasurementCategory $measurementCategory, \DateTimeImmutable $now): self
    {
        return new self($id, CatalogName::normalize($name, 'PRD-01'), $measurementCategory, $now);
    }

    public function id(): ProductId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function measurementCategory(): MeasurementCategory
    {
        return $this->measurementCategory;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
