<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Persistence\Type;

use App\Shared\Infrastructure\Persistence\Type\DateTimeTzMicroImmutableType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use PHPUnit\Framework\TestCase;

final class DateTimeTzMicroImmutableTypeTest extends TestCase
{
    private DateTimeTzMicroImmutableType $type;

    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new DateTimeTzMicroImmutableType();
        $this->platform = new PostgreSQLPlatform();
    }

    public function testDeclaresATimestampWithMicroseconds(): void
    {
        self::assertSame('TIMESTAMP(6) WITH TIME ZONE', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testWritesMicrosecondsAndTheOffset(): void
    {
        $moment = new \DateTimeImmutable('2026-11-25 10:15:30.123456', new \DateTimeZone('+01:00'));

        self::assertSame('2026-11-25 10:15:30.123456+01:00', $this->type->convertToDatabaseValue($moment, $this->platform));
    }

    public function testReadsWhatPostgresqlPrints(): void
    {
        $moment = $this->type->convertToPHPValue('2026-11-25 09:15:30.123456+00', $this->platform);

        self::assertSame('2026-11-25T09:15:30.123456+00:00', $moment->format('Y-m-d\TH:i:s.uP'));
    }

    public function testReadsAMomentWhoseFractionPostgresqlLeftOut(): void
    {
        $moment = $this->type->convertToPHPValue('2026-11-25 09:15:30+00', $this->platform);

        self::assertSame('2026-11-25T09:15:30.000000+00:00', $moment->format('Y-m-d\TH:i:s.uP'));
    }

    public function testRefusesAMutableDateTime(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue(new \DateTime(), $this->platform);
    }

    public function testRefusesAStringThatIsNotATimestamp(): void
    {
        $this->expectException(InvalidFormat::class);

        $this->type->convertToPHPValue('yesterday', $this->platform);
    }
}
