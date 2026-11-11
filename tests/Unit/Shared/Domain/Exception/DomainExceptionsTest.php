<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomainExceptionsTest extends TestCase
{
    public function testValidationExceptionCarriesCodeMessageAndField(): void
    {
        $exception = new ValidationException('QTY-01', 'Quantity must be positive.', 'quantity');

        self::assertSame('QTY-01', $exception->errorCode());
        self::assertSame('Quantity must be positive.', $exception->getMessage());
        self::assertSame('quantity', $exception->field());
    }

    public function testValidationExceptionFieldIsOptional(): void
    {
        $exception = new ValidationException('QTY-01', 'Quantity must be positive.');

        self::assertNull($exception->field());
    }

    public function testInvariantCodeDoesNotLeakIntoTheBuiltInIntegerCode(): void
    {
        $exception = new ValidationException('QTY-01', 'Quantity must be positive.');

        self::assertSame(0, $exception->getCode());
    }

    public function testNotFoundExceptionKeepsResourceAndIdAndBuildsTheMessage(): void
    {
        $exception = new NotFoundException('Product', '0193a5e0-7b1c-7a3e-9f00-0123456789ab');

        self::assertSame('Product', $exception->resource());
        self::assertSame('0193a5e0-7b1c-7a3e-9f00-0123456789ab', $exception->id());
        self::assertSame('Product "0193a5e0-7b1c-7a3e-9f00-0123456789ab" was not found.', $exception->getMessage());
    }

    public function testConflictExceptionCarriesCodeAndMessage(): void
    {
        $exception = new ConflictException('NAME-01', 'A product with this name already exists.');

        self::assertSame('NAME-01', $exception->errorCode());
        self::assertSame('A product with this name already exists.', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{\Throwable}>
     */
    public static function exceptions(): iterable
    {
        yield 'validation' => [new ValidationException('QTY-01', 'Quantity must be positive.')];
        yield 'not found' => [new NotFoundException('Product', 'missing')];
        yield 'conflict' => [new ConflictException('NAME-01', 'Duplicate name.')];
    }

    /**
     * Outer layers rely on a single catch for everything the domain refuses.
     */
    #[DataProvider('exceptions')]
    public function testEveryExceptionSharesTheDomainParent(\Throwable $exception): void
    {
        self::assertInstanceOf(DomainException::class, $exception);
        self::assertInstanceOf(\DomainException::class, $exception);
    }
}
