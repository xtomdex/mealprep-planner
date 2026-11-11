<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Identity;

use App\Shared\Infrastructure\Identity\SymfonyUuidGenerator;
use PHPUnit\Framework\TestCase;

final class SymfonyUuidGeneratorTest extends TestCase
{
    /**
     * Canonical lowercase form with the version nibble fixed to 7 and the
     * RFC variant bits (8, 9, a or b) in place.
     */
    private const string UUID_V7 = '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/';

    public function testGeneratesACanonicalUuidOfVersionSeven(): void
    {
        self::assertMatchesRegularExpression(self::UUID_V7, (new SymfonyUuidGenerator())->nextId());
    }

    public function testEveryIdIsDifferent(): void
    {
        $generator = new SymfonyUuidGenerator();

        $ids = [];
        for ($i = 0; $i < 1000; ++$i) {
            $ids[] = $generator->nextId();
        }

        self::assertCount(1000, array_unique($ids));
    }

    /**
     * Many ids fall into the same millisecond here, so this also covers the
     * ordering inside one timestamp, not only across timestamps.
     */
    public function testLaterIdsSortAfterEarlierOnes(): void
    {
        $generator = new SymfonyUuidGenerator();

        $ids = [];
        for ($i = 0; $i < 1000; ++$i) {
            $ids[] = $generator->nextId();
        }

        $sorted = $ids;
        sort($sorted, \SORT_STRING);

        self::assertSame($sorted, $ids);
    }

    public function testIdsSortByTimeAcrossMilliseconds(): void
    {
        $generator = new SymfonyUuidGenerator();

        $earlier = $generator->nextId();
        usleep(2000);
        $later = $generator->nextId();

        self::assertLessThan(0, strcmp($earlier, $later));
    }
}
