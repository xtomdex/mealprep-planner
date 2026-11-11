<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\Product;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    private const string ID = '0193a5e0-7b1c-7a3e-9f00-0123456789ab';

    public function testCreateKeepsWhatItWasGiven(): void
    {
        $id = new ProductId(self::ID);
        $now = new \DateTimeImmutable('2026-11-11 10:00:00');

        $product = Product::create($id, 'Basmati rice', MeasurementCategory::Weight, $now);

        self::assertSame($id, $product->id());
        self::assertSame('Basmati rice', $product->name());
        self::assertSame(MeasurementCategory::Weight, $product->measurementCategory());
        self::assertSame($now, $product->createdAt());
    }

    public function testPrd01StoresTheTrimmedName(): void
    {
        $product = Product::create(new ProductId(self::ID), '  Olive oil  ', MeasurementCategory::Volume, new \DateTimeImmutable());

        self::assertSame('Olive oil', $product->name());
    }

    public function testPrd01RejectsABlankName(): void
    {
        try {
            Product::create(new ProductId(self::ID), '   ', MeasurementCategory::Weight, new \DateTimeImmutable());
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame('PRD-01', $e->errorCode());
            self::assertSame('name', $e->field());
        }
    }

    public function testPrd01RejectsANameLongerThan120Characters(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at most 120');

        Product::create(new ProductId(self::ID), str_repeat('a', 121), MeasurementCategory::Weight, new \DateTimeImmutable());
    }
}
