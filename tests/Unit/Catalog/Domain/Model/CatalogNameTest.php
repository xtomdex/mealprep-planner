<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\CatalogName;
use App\Shared\Domain\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CatalogNameTest extends TestCase
{
    public function testReturnsTheTrimmedName(): void
    {
        self::assertSame('Basmati rice', CatalogName::normalize("  Basmati rice \n", 'XXX-01'));
    }

    public function testAcceptsExactlyTheMaximumLengthCountedInCharacters(): void
    {
        // 120 two-byte characters: 240 bytes, still 120 characters.
        $name = str_repeat('é', CatalogName::MAX_LENGTH);

        self::assertSame($name, CatalogName::normalize($name, 'XXX-01'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => [" \t\n "];
        yield 'one character too long' => [str_repeat('a', CatalogName::MAX_LENGTH + 1)];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsAnInvalidNameWithTheCallersCode(string $name): void
    {
        try {
            CatalogName::normalize($name, 'XXX-01');
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame('XXX-01', $e->errorCode());
            self::assertSame('name', $e->field());
        }
    }
}
