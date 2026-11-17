<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Domain\Model;

use App\Planning\Domain\Model\ComponentRequirement;
use App\Planning\Domain\Model\PlanRequirements;
use App\Planning\Domain\Model\ProductRequirement;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use PHPUnit\Framework\TestCase;

final class PlanRequirementsTest extends TestCase
{
    private const string RICE = '0193a5e0-7b1c-7a3e-9f00-0000000000a1';
    private const string OIL = '0193a5e0-7b1c-7a3e-9f00-0000000000a2';
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';

    public function testComponentRequirementAcceptsFractionalPortions(): void
    {
        $requirement = new ComponentRequirement(new ComponentId(self::SAUCE), 'Tomato sauce', Quantity::of('0.5', Unit::Portion));

        self::assertSame('0.5 portion', (string) $requirement->portions);
        self::assertSame('Tomato sauce', $requirement->componentName);
    }

    public function testComponentRequirementRejectsAnythingButPortions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::SAUCE);

        new ComponentRequirement(new ComponentId(self::SAUCE), 'Tomato sauce', Quantity::of('500', Unit::Gram));
    }

    public function testListsAreKeptInTheOrderGiven(): void
    {
        $rice = new ProductRequirement(new ProductId(self::RICE), 'Rice', Quantity::of('1', Unit::Kilogram));
        $oil = new ProductRequirement(new ProductId(self::OIL), 'Olive oil', Quantity::of('20', Unit::Millilitre));
        $sauce = new ComponentRequirement(new ComponentId(self::SAUCE), 'Tomato sauce', Quantity::of('2', Unit::Portion));

        $requirements = new PlanRequirements([$rice, $oil], [$sauce]);

        self::assertSame([$rice, $oil], $requirements->products);
        self::assertSame([$sauce], $requirements->components);
    }
}
