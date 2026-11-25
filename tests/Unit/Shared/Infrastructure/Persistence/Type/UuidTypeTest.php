<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Persistence\Type;

use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Infrastructure\Persistence\Type\ProductIdType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\TestCase;

/**
 * The base type is tested through one subclass: the subclasses only name
 * their id class, and IdTypesRegistrationTest covers each of them.
 */
final class UuidTypeTest extends TestCase
{
    private const string ID = '0193a4b2-7c1d-7e2f-8a3b-4c5d6e7f8091';

    private ProductIdType $type;

    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new ProductIdType();
        $this->platform = new PostgreSQLPlatform();
    }

    public function testDeclaresANativeUuidColumn(): void
    {
        self::assertSame('UUID', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testWritesTheIdAsItsCanonicalString(): void
    {
        self::assertSame(self::ID, $this->type->convertToDatabaseValue(new ProductId(self::ID), $this->platform));
    }

    public function testReadsTheStringBackAsTheTypedId(): void
    {
        $id = $this->type->convertToPHPValue(self::ID, $this->platform);

        self::assertInstanceOf(ProductId::class, $id);
        self::assertTrue($id->equals(new ProductId(self::ID)));
    }

    public function testNullStaysNullInBothDirections(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testRefusesAnIdOfAnotherType(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue(new DishId(self::ID), $this->platform);
    }

    public function testRefusesABareStringOnTheWayIn(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue(self::ID, $this->platform);
    }

    public function testRefusesAMalformedValueFromTheDatabase(): void
    {
        $this->expectException(ValueNotConvertible::class);

        $this->type->convertToPHPValue('not-a-uuid', $this->platform);
    }
}
