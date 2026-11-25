<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Completes the schema Doctrine generates from the mapping with the foreign
 * keys the mapping does not carry, and gives every foreign key a name.
 *
 * Aggregates refer to each other by id, not by ORM association: a recipe
 * line holds a ProductId, not a Product. That keeps the aggregate boundary
 * where the domain draws it, and loading a dish never drags the catalog
 * behind it. Without an association Doctrine generates no foreign key, so
 * the database would accept a line pointing at a product that does not
 * exist. The keys are declared here instead: even if code is wrong, the
 * database refuses a dangling reference.
 *
 * Declaring them on the generated schema, rather than only in a migration,
 * keeps migrations:diff and schema:validate honest: both compare the
 * database with this schema, so a missing key shows up as a difference.
 *
 * Doctrine names the keys it generates itself (owner associations) with a
 * hash; they are renamed to fk_<table>_<column> like the declared ones, so
 * an error message names the column that broke.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class ForeignKeySchema
{
    /**
     * Single-column references between aggregates, all to an "id" column.
     * RESTRICT: something still in use cannot be deleted.
     *
     * @var list<array{string, string, string, ReferentialAction}> table, column, referenced table, on delete
     */
    private const array REFERENCES = [
        ['catalog_component_ingredients', 'product_id', 'catalog_products', ReferentialAction::RESTRICT],
        ['catalog_component_ingredients', 'child_component_id', 'catalog_components', ReferentialAction::RESTRICT],
        ['catalog_dish_ingredients', 'product_id', 'catalog_products', ReferentialAction::RESTRICT],
        ['catalog_dish_ingredients', 'component_id', 'catalog_components', ReferentialAction::RESTRICT],
        ['planning_meal_plan_entries', 'dish_id', 'catalog_dishes', ReferentialAction::RESTRICT],
    ];

    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();
        $editor = $schema->edit();

        foreach ($schema->getTables() as $table) {
            $tableName = $table->getObjectName()->toString();
            $generatedKeys = $table->getForeignKeys();
            if ([] === $generatedKeys) {
                continue;
            }

            $editor->modifyTable($table->getObjectName(), static function (TableEditor $table) use ($tableName, $generatedKeys): void {
                foreach ($generatedKeys as $key) {
                    $columns = array_map(static fn (UnqualifiedName $column): string => $column->toString(), $key->getReferencingColumnNames());
                    $table
                        ->dropForeignKeyConstraint($key->getObjectName() ?? throw new \LogicException(\sprintf('A generated foreign key on %s has no name.', $tableName)))
                        ->addForeignKeyConstraint($key->edit()->setUnquotedName(self::name($tableName, implode('_', $columns)))->create());
                }
            });
        }

        // Fails loudly on a table that is not in the schema: a typo here must
        // not silently leave a reference unchecked.
        foreach (self::REFERENCES as [$tableName, $column, $referencedTable, $onDelete]) {
            $editor->modifyTableByUnquotedName($tableName, static function (TableEditor $table) use ($tableName, $column, $referencedTable, $onDelete): void {
                $table->addForeignKeyConstraint(
                    ForeignKeyConstraint::editor()
                        ->setUnquotedName(self::name($tableName, $column))
                        ->setUnquotedReferencingColumnNames($column)
                        ->setUnquotedReferencedTableName($referencedTable)
                        ->setUnquotedReferencedColumnNames('id')
                        ->setOnDeleteAction($onDelete)
                        ->create(),
                );
            });
        }

        $args->setSchema($editor->create());
    }

    /**
     * @return non-empty-string
     */
    private static function name(string $table, string $columns): string
    {
        return \sprintf('fk_%s_%s', $table, $columns);
    }
}
