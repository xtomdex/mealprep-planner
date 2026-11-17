<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\CreateComponent\CreateComponent;
use App\Catalog\Application\Command\CreateComponent\CreateComponentHandler;
use App\Catalog\Domain\Model\ComponentIngredient;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Service\IngredientReferenceGuard;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\MeasurementCategory;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use App\Tests\Support\Catalog\InMemoryComponentRepository;
use App\Tests\Support\Catalog\InMemoryProductRepository;
use App\Tests\Support\Shared\ImmediateTransactionalRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreateComponentHandlerTest extends TestCase
{
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';
    private const string TOMATO = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string OIL = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string UNKNOWN = '0193a5e0-7b1c-7a3e-9f00-0000000000ff';

    private InMemoryComponentRepository $components;

    private ImmediateTransactionalRunner $transaction;

    private MockClock $clock;

    private CreateComponentHandler $handler;

    protected function setUp(): void
    {
        $products = new InMemoryProductRepository();
        $products->add(Product::create(new ProductId(self::TOMATO), 'Tomato', MeasurementCategory::Weight, new \DateTimeImmutable()));
        $products->add(Product::create(new ProductId(self::OIL), 'Olive oil', MeasurementCategory::Volume, new \DateTimeImmutable()));

        $this->components = new InMemoryComponentRepository();
        $this->transaction = new ImmediateTransactionalRunner();
        $this->clock = new MockClock('2026-11-17 10:00:00');
        $this->handler = new CreateComponentHandler($this->components, new IngredientReferenceGuard($products, $this->components), $this->transaction, $this->clock);
    }

    public function testStoresTheComponentWithItsLinesInRecipeOrder(): void
    {
        ($this->handler)(new CreateComponent(new ComponentId(self::SAUCE), 'Tomato sauce', 4, [
            $this->line(self::TOMATO, '800', Unit::Gram),
            $this->line(self::OIL, '40', Unit::Millilitre),
        ]));

        $sauce = $this->components->get(new ComponentId(self::SAUCE));
        self::assertSame(['Tomato sauce', 4], [$sauce->name(), $sauce->yieldPortions()]);
        self::assertSame(['800 g', '40 ml'], array_map(static fn (ComponentIngredient $i): string => (string) $i->quantity(), $sauce->ingredients()));
        self::assertEquals($this->clock->now(), $sauce->createdAt());
        self::assertSame(1, $this->transaction->runs());
    }

    public function testIng01AnUnknownProductStopsCreationAndNothingIsStored(): void
    {
        $this->assertRejected('ING-01', new CreateComponent(new ComponentId(self::SAUCE), 'Tomato sauce', 4, [
            $this->line(self::TOMATO, '800', Unit::Gram),
            $this->line(self::UNKNOWN, '5', Unit::Gram),
        ]));
    }

    public function testCmp03AYieldBelowOneReachesTheCallerUnchangedAndNothingIsStored(): void
    {
        $this->assertRejected('CMP-03', new CreateComponent(new ComponentId(self::SAUCE), 'Tomato sauce', 0, [
            $this->line(self::TOMATO, '800', Unit::Gram),
        ]));
    }

    private function line(string $productId, string $amount, Unit $unit): IngredientLine
    {
        return new IngredientLine(IngredientRef::product(new ProductId($productId)), Quantity::of($amount, $unit));
    }

    private function assertRejected(string $code, CreateComponent $command): void
    {
        try {
            ($this->handler)($command);
            self::fail(\sprintf('Expected %s.', $code));
        } catch (ValidationException $e) {
            self::assertSame($code, $e->errorCode());
        }

        self::assertSame([], $this->components->all());
    }
}
