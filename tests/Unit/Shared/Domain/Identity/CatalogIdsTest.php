<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Identity;

use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Identity\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CatalogIdsTest extends TestCase
{
    private const string VALUE = '0193a5e0-7b1c-7a3e-9f00-0123456789ab';

    /**
     * @return iterable<string, array{class-string<Uuid>}>
     */
    public static function idClasses(): iterable
    {
        yield 'product' => [ProductId::class];
        yield 'component' => [ComponentId::class];
        yield 'dish' => [DishId::class];
    }

    /**
     * @param class-string<Uuid> $class
     */
    #[DataProvider('idClasses')]
    public function testConstructsFromACanonicalUuid(string $class): void
    {
        $id = new $class(self::VALUE);

        self::assertSame(self::VALUE, $id->value);
        self::assertSame(self::VALUE, (string) $id);
        self::assertTrue($id->equals(new $class(self::VALUE)));
    }

    /**
     * @param class-string<Uuid> $class
     */
    #[DataProvider('idClasses')]
    public function testRejectsAMalformedValue(string $class): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new $class('not-a-uuid');
    }

    public function testIdsOfDifferentTypesNeverEqualEvenWithTheSameValue(): void
    {
        $product = new ProductId(self::VALUE);
        $component = new ComponentId(self::VALUE);
        $dish = new DishId(self::VALUE);

        self::assertFalse($dish->equals($product));
        self::assertFalse($product->equals($dish));
        self::assertFalse($product->equals($component));
        self::assertFalse($component->equals($product));
        self::assertFalse($component->equals($dish));
        self::assertFalse($dish->equals($component));
    }
}
