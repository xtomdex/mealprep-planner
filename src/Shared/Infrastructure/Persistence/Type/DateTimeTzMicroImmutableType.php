<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;

/**
 * A moment as timestamptz(6): the instant with microseconds, the offset it
 * was written with, read back as a DateTimeImmutable.
 *
 * DBAL's own datetimetz_immutable declares TIMESTAMP(0) on PostgreSQL and
 * formats without fractions, so a moment would lose its microseconds on the
 * way in. Two changes in time order (an entry added, then the list
 * generated) could then come back as equal, and staleness compares exactly
 * those moments.
 *
 * PostgreSQL only, like the rest of the schema. doctrine.yaml maps the
 * database type timestamptz to this type, so an introspected column
 * compares equal to the mapping; the cost is that a column created as
 * timestamptz(0) by hand would not show up as a difference.
 */
final class DateTimeTzMicroImmutableType extends DateTimeTzImmutableType
{
    public const string NAME = 'datetimetz_micro_immutable';

    private const string FORMAT = 'Y-m-d H:i:s.uP';

    // PostgreSQL leaves out the fraction when it is zero: "10:00:00+00".
    private const string FORMAT_WITHOUT_FRACTION = 'Y-m-d H:i:sP';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TIMESTAMP(6) WITH TIME ZONE';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value->format(self::FORMAT);
        }

        throw InvalidType::new($value, self::class, ['null', \DateTimeImmutable::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        if (\is_string($value)) {
            // "!" resets every field the format does not set, so nothing of
            // the current time leaks into the result.
            foreach ([self::FORMAT, self::FORMAT_WITHOUT_FRACTION] as $format) {
                $moment = \DateTimeImmutable::createFromFormat('!'.$format, $value);
                if (false !== $moment) {
                    return $moment;
                }
            }
        }

        throw InvalidFormat::new(\is_string($value) ? $value : get_debug_type($value), self::class, self::FORMAT);
    }
}
