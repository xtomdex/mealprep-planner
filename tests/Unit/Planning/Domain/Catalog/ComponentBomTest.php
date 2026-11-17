<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planning\Domain\Catalog;

use App\Planning\Domain\Catalog\ComponentBom;
use App\Planning\Domain\Exception\InconsistentCatalogData;
use App\Shared\Domain\Identity\ComponentId;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ComponentBomTest extends TestCase
{
    private const string SAUCE = '0193a5e0-7b1c-7a3e-9f00-0000000000c1';

    #[TestWith([0])]
    #[TestWith([-2])]
    public function testAYieldBelowOnePortionIsAnIntegrityErrorNamingTheComponent(int $yield): void
    {
        $this->expectException(InconsistentCatalogData::class);
        $this->expectExceptionMessage(\sprintf('Component %s yields %d portions', self::SAUCE, $yield));

        new ComponentBom(new ComponentId(self::SAUCE), 'Tomato sauce', $yield, [], []);
    }

    public function testAYieldOfOnePortionIsAccepted(): void
    {
        self::assertSame(1, (new ComponentBom(new ComponentId(self::SAUCE), 'Tomato sauce', 1, [], []))->yieldPortions);
    }
}
