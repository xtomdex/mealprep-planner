<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Persistence\Type;

use App\Shared\Infrastructure\Persistence\Type\DecimalAmountType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalAmountTypeTest extends TestCase
{
    private DecimalAmountType $type;

    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new DecimalAmountType();
        $this->platform = new PostgreSQLPlatform();
    }

    public function testDeclaresTheNumericColumnTheMappingAsksFor(): void
    {
        self::assertSame('NUMERIC(14, 6)', $this->type->getSQLDeclaration(['precision' => 14, 'scale' => 6], $this->platform));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function paddedAmounts(): iterable
    {
        yield 'whole number' => ['150.000000', '150'];
        yield 'fraction' => ['0.500000', '0.5'];
        yield 'zeros of a whole number are kept' => ['1000.000000', '1000'];
        yield 'smallest step' => ['0.000001', '0.000001'];
        yield 'already short' => ['42', '42'];
    }

    #[DataProvider('paddedAmounts')]
    public function testReadsAnAmountInItsShortestSpelling(string $fromDatabase, string $expected): void
    {
        self::assertSame($expected, $this->type->convertToPHPValue($fromDatabase, $this->platform));
    }

    public function testWritesTheAmountAsItIs(): void
    {
        self::assertSame('1.5', $this->type->convertToDatabaseValue('1.5', $this->platform));
    }

    public function testNullStaysNullInBothDirections(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testRefusesAValueThatIsNotADecimal(): void
    {
        $this->expectException(ValueNotConvertible::class);

        $this->type->convertToPHPValue('1e3', $this->platform);
    }
}
