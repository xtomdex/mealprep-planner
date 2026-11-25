<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Persistence;

use App\Catalog\Domain\Model\Product;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Tests\Support\Persistence\MappingTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class ProductMappingTest extends MappingTestCase
{
    public function testAProductRoundTripsWithEveryField(): void
    {
        $id = new ProductId(self::nextId());
        $createdAt = new \DateTimeImmutable('2026-11-25 10:15:30.123456', new \DateTimeZone('Europe/Madrid'));
        $this->em()->persist(Product::create($id, 'Basmati rice', MeasurementCategory::Weight, $createdAt));
        $this->flushAndClear();

        $loaded = $this->em()->find(Product::class, $id);

        self::assertNotNull($loaded);
        self::assertTrue($loaded->id()->equals($id));
        self::assertSame('Basmati rice', $loaded->name());
        self::assertSame(MeasurementCategory::Weight, $loaded->measurementCategory());
        // The same instant down to the microsecond; the offset may differ.
        self::assertSame($createdAt->format('U.u'), $loaded->createdAt()->format('U.u'));
    }

    public function testASecondProductWithTheSameNameIsRefusedByItsNamedConstraint(): void
    {
        $now = new \DateTimeImmutable('2026-11-25 10:00:00');
        $this->em()->persist(Product::create(new ProductId(self::nextId()), 'Rice', MeasurementCategory::Weight, $now));
        $this->em()->flush();

        $this->em()->persist(Product::create(new ProductId(self::nextId()), 'Rice', MeasurementCategory::Count, $now));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->expectExceptionMessage('uq_catalog_products_name');

        $this->em()->flush();
    }
}
