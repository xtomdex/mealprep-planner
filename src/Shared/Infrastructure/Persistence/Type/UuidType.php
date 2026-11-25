<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Type;

use App\Shared\Domain\Identity\Uuid;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Stores a typed id in a native PostgreSQL uuid column (16 bytes, compared
 * as a number) and turns it back into the same typed id on the way out, so
 * an entity never holds a bare string where the domain expects a ProductId.
 *
 * One subclass per id class instead of one type that guesses the class: the
 * mapping then says which id a column holds, and a DishId written into a
 * product_id column is refused here instead of being stored as a valid uuid.
 *
 * @template T of Uuid
 */
abstract class UuidType extends Type
{
    /**
     * @return class-string<T>
     */
    abstract protected function idClass(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        $class = $this->idClass();
        if ($value instanceof $class) {
            return $value->value;
        }

        throw InvalidType::new($value, static::class, ['null', $class]);
    }

    /**
     * PostgreSQL prints a uuid in the canonical lowercase form, which is the
     * only form the domain accepts, so the string goes into the id as is.
     *
     * @return T|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Uuid
    {
        if (null === $value) {
            return null;
        }

        $class = $this->idClass();
        if ($value instanceof $class) {
            return $value;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, static::class);
        }

        try {
            return new $class($value);
        } catch (\InvalidArgumentException $e) {
            throw ValueNotConvertible::new($value, static::class, null, $e);
        }
    }
}
