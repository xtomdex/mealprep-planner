<?php

declare(strict_types=1);

namespace App\Planning\Infrastructure\Persistence;

use App\Planning\Domain\Model\ShoppingItem;
use App\Planning\Domain\Model\ShoppingList;
use App\Planning\Infrastructure\Persistence\Type\ShoppingListIdType;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Lets a regenerated shopping list be stored in the same flush that
 * replaced its items.
 *
 * ShoppingList::replaceItems() clears the items and adds new ones at
 * positions 1..n. Items are keyed by (list, position), so a new item has the
 * key of an old one, and the old one is still managed until its orphan
 * delete runs; Doctrine refuses the second object with that key in its
 * identity map. Even past that, Doctrine inserts before it deletes, so the
 * database would see the key twice. Before the flush computes anything,
 * this listener therefore takes the old items out of the unit of work and
 * deletes their rows with one statement; the flush then only inserts.
 *
 * The domain is not bent for this: no surrogate id on ShoppingItem, no
 * second flush in the handler. The statement runs inside the runner's
 * transaction, so a failed flush rolls the delete back with it.
 *
 * UnitOfWork::cancelOrphanRemoval() is marked @ignore in the ORM (public,
 * but not promised as API). ShoppingListItemsReplacementTest is the guard:
 * an ORM upgrade that changes it fails there first.
 */
#[AsDoctrineListener(event: Events::preFlush)]
final class ShoppingListItemsReplacement
{
    public function preFlush(PreFlushEventArgs $args): void
    {
        $manager = $args->getObjectManager();
        $unitOfWork = $manager->getUnitOfWork();
        $identityMap = $unitOfWork->getIdentityMap();

        foreach ($identityMap[ShoppingList::class] ?? [] as $list) {
            if (!$list instanceof ShoppingList || $unitOfWork->isUninitializedObject($list)) {
                continue;
            }

            $stale = $this->replacedItems($list, $identityMap[ShoppingItem::class] ?? []);
            if ([] === $stale) {
                continue;
            }

            foreach ($stale as $item) {
                $unitOfWork->cancelOrphanRemoval($item);
                $manager->detach($item);
            }

            $manager->createQueryBuilder()
                ->delete(ShoppingItem::class, 'i')
                ->where('i.shoppingList = :list')
                ->setParameter('list', $list->id(), ShoppingListIdType::NAME)
                ->getQuery()
                ->execute();
        }
    }

    /**
     * Managed items of this list that are no longer in it: exactly the ones
     * replaceItems() dropped.
     *
     * @param array<object> $managedItems
     *
     * @return list<ShoppingItem>
     */
    private function replacedItems(ShoppingList $list, array $managedItems): array
    {
        $ofThisList = array_filter($managedItems, static fn (object $item): bool => $item instanceof ShoppingItem && $item->shoppingList() === $list);
        if ([] === $ofThisList) {
            return [];
        }

        $current = $list->items();

        return array_values(array_filter($ofThisList, static fn (ShoppingItem $item): bool => !\in_array($item, $current, true)));
    }
}
