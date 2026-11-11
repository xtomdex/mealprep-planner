<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\Composition;
use App\Catalog\Domain\Model\IngredientLine;
use App\Catalog\Domain\Model\IngredientRef;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Measurement\Quantity;
use App\Shared\Domain\Measurement\Unit;
use PHPUnit\Framework\TestCase;

final class CompositionTest extends TestCase
{
    private const string A = '0193a5e0-7b1c-7a3e-9f00-00000000000a';
    private const string B = '0193a5e0-7b1c-7a3e-9f00-00000000000b';

    public function testAcceptsDistinctLines(): void
    {
        $this->expectNotToPerformAssertions();

        Composition::assertValid([$this->line(IngredientRef::product(new ProductId(self::A))), $this->line(IngredientRef::product(new ProductId(self::B)))], 'E-EMPTY', 'E-DUP');
    }

    public function testRejectsAnEmptyListWithTheEmptyCode(): void
    {
        try {
            Composition::assertValid([], 'E-EMPTY', 'E-DUP');
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame('E-EMPTY', $e->errorCode());
            self::assertSame('ingredients', $e->field());
        }
    }

    public function testRejectsARepeatedRefWithTheDuplicateCodeAndNamesBothPositions(): void
    {
        $ref = IngredientRef::component(new ComponentId(self::A));

        try {
            Composition::assertValid([$this->line($ref), $this->line(IngredientRef::component(new ComponentId(self::A)))], 'E-EMPTY', 'E-DUP');
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame('E-DUP', $e->errorCode());
            self::assertSame('Line 2 repeats the ingredient of line 1 (component '.self::A.').', $e->getMessage());
        }
    }

    private function line(IngredientRef $ref): IngredientLine
    {
        return new IngredientLine($ref, Quantity::of('1', Unit::Portion));
    }
}
