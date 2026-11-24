<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\ListProducts\ListProducts;
use App\Catalog\Application\Query\ListProducts\ListProductsHandler;
use App\Catalog\Application\View\ProductView;
use App\Catalog\Domain\Model\Product;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Tests\Support\Catalog\InMemoryProductRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ListProductsHandlerTest extends TestCase
{
    private const string APPLE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string BANANA = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string CHERRY = '0193a5e0-7b1c-7a3e-9f00-0000000000a3';

    private InMemoryProductRepository $products;

    private MockClock $clock;

    private ListProductsHandler $handler;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->clock = new MockClock('2026-11-24 10:00:00');
        $this->handler = new ListProductsHandler($this->products);
    }

    public function testProductsComeBackSortedByNameIgnoringCase(): void
    {
        // Added out of order, and with a capital B that a byte-wise sort would put first.
        $this->add(self::CHERRY, 'cherry', MeasurementCategory::Count);
        $this->add(self::APPLE, 'apple', MeasurementCategory::Weight);
        $this->add(self::BANANA, 'Banana', MeasurementCategory::Weight);

        $views = ($this->handler)(new ListProducts());

        self::assertEquals([
            new ProductView(self::APPLE, 'apple', 'weight'),
            new ProductView(self::BANANA, 'Banana', 'weight'),
            new ProductView(self::CHERRY, 'cherry', 'count'),
        ], $views);
    }

    public function testAnEmptyCatalogGivesAnEmptyList(): void
    {
        self::assertSame([], ($this->handler)(new ListProducts()));
    }

    private function add(string $id, string $name, MeasurementCategory $category): void
    {
        $this->products->add(Product::create(new ProductId($id), $name, $category, $this->clock->now()));
    }
}
