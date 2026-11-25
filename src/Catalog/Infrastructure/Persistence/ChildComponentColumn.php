<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Model\ComponentIngredient;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

/**
 * Renames one embedded column on component lines: the component an
 * IngredientRef points at is stored in child_component_id, because
 * component_id on that table is the owner.
 *
 * An embeddable has one mapping for every entity that embeds it, and
 * Doctrine has no per-entity override for embedded columns (attribute
 * overrides only reach inherited fields). Dish lines keep the embeddable's
 * own name, component_id; this listener changes the one entity that needs
 * another name, after Doctrine has copied the embeddable's columns into it.
 * The alternatives were worse: a column prefix renames every column of the
 * embeddable, and a custom naming strategy would hide the rule in a global
 * setting.
 */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
final class ChildComponentColumn
{
    private const string FIELD = 'ref.componentId';

    private const string COLUMN = 'child_component_id';

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();
        if (ComponentIngredient::class !== $metadata->getName()) {
            return;
        }

        // Every lookup the ORM makes by column or by field reads one of these
        // three places, so all three move together.
        $mapping = $metadata->getFieldMapping(self::FIELD);
        unset($metadata->fieldNames[$mapping->columnName]);
        $mapping->columnName = self::COLUMN;
        $metadata->fieldNames[self::COLUMN] = self::FIELD;
        $metadata->columnNames[self::FIELD] = self::COLUMN;
    }
}
