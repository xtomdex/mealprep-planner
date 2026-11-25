<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Persistence;

use App\Planning\Domain\Model\MealPlanEntryId;
use App\Planning\Domain\Model\MealPlanId;
use App\Planning\Domain\Model\ShoppingListId;
use App\Planning\Infrastructure\Persistence\Type\MealPlanEntryIdType;
use App\Planning\Infrastructure\Persistence\Type\MealPlanIdType;
use App\Planning\Infrastructure\Persistence\Type\ShoppingListIdType;
use App\Shared\Domain\Identity\ComponentId;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Infrastructure\Persistence\Type\ComponentIdType;
use App\Shared\Infrastructure\Persistence\Type\DishIdType;
use App\Shared\Infrastructure\Persistence\Type\ProductIdType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * doctrine.yaml registers every id type under the name its class declares,
 * and each type hands back its own id class. Needs the container, not the
 * database: types are registered when the connection is built.
 */
final class IdTypesRegistrationTest extends KernelTestCase
{
    private const string ID = '0193a4b2-7c1d-7e2f-8a3b-4c5d6e7f8091';

    /**
     * @return iterable<string, array{string, class-string<Type>, class-string}>
     */
    public static function idTypes(): iterable
    {
        yield 'product' => [ProductIdType::NAME, ProductIdType::class, ProductId::class];
        yield 'component' => [ComponentIdType::NAME, ComponentIdType::class, ComponentId::class];
        yield 'dish' => [DishIdType::NAME, DishIdType::class, DishId::class];
        yield 'meal plan' => [MealPlanIdType::NAME, MealPlanIdType::class, MealPlanId::class];
        yield 'meal plan entry' => [MealPlanEntryIdType::NAME, MealPlanEntryIdType::class, MealPlanEntryId::class];
        yield 'shopping list' => [ShoppingListIdType::NAME, ShoppingListIdType::class, ShoppingListId::class];
    }

    /**
     * @param class-string<Type> $typeClass
     * @param class-string       $idClass
     */
    #[DataProvider('idTypes')]
    public function testTheTypeIsRegisteredAndReturnsItsIdClass(string $name, string $typeClass, string $idClass): void
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        $type = Type::getType($name);

        self::assertInstanceOf($typeClass, $type);
        self::assertInstanceOf($idClass, $type->convertToPHPValue(self::ID, $connection->getDatabasePlatform()));
    }
}
