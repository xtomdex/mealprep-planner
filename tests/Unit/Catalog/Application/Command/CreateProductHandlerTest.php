<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\CreateProduct\CreateProduct;
use App\Catalog\Application\Command\CreateProduct\CreateProductHandler;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Tests\Support\Catalog\InMemoryProductRepository;
use App\Tests\Support\Shared\ImmediateTransactionalRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreateProductHandlerTest extends TestCase
{
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';

    private InMemoryProductRepository $products;

    private ImmediateTransactionalRunner $transaction;

    private MockClock $clock;

    private CreateProductHandler $handler;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->transaction = new ImmediateTransactionalRunner();
        $this->clock = new MockClock('2026-11-17 10:00:00');
        $this->handler = new CreateProductHandler($this->products, $this->transaction, $this->clock);
    }

    public function testStoresTheProductWithTheClocksTimeInsideATransaction(): void
    {
        ($this->handler)(new CreateProduct(new ProductId(self::RICE), '  Rice ', MeasurementCategory::Weight));

        $product = $this->products->get(new ProductId(self::RICE));
        self::assertSame('Rice', $product->name());
        self::assertSame(MeasurementCategory::Weight, $product->measurementCategory());
        self::assertEquals($this->clock->now(), $product->createdAt());
        self::assertSame(1, $this->transaction->runs());
    }

    public function testPrd01AnEmptyNameReachesTheCallerAsValidationExceptionAndNothingIsStored(): void
    {
        try {
            ($this->handler)(new CreateProduct(new ProductId(self::RICE), '   ', MeasurementCategory::Weight));
            self::fail('Expected PRD-01.');
        } catch (ValidationException $e) {
            self::assertSame('PRD-01', $e->errorCode());
        }

        self::assertSame([], $this->products->all());
    }
}
