<?php

declare(strict_types=1);

namespace App\Planning\Domain\Service;

use App\Planning\Domain\Catalog\CatalogReader;
use App\Planning\Domain\Catalog\DishComposition;
use App\Planning\Domain\Catalog\ProductInfo;
use App\Planning\Domain\Exception\MissingCatalogData;
use App\Planning\Domain\Model\MealPlan;
use App\Planning\Domain\Model\PlanRequirements;
use App\Planning\Domain\Model\ProductRequirement;
use App\Shared\Domain\Identity\DishId;
use App\Shared\Domain\Identity\ProductId;
use App\Shared\Domain\Identity\Uuid;

/**
 * Turns a meal plan into what it needs: how much of each product, and how
 * many portions of each component, the whole plan takes.
 *
 * A pure function of the plan and the catalog: nothing is stored, and the
 * same input gives the same output, sorted by name. The plan's status does
 * not matter (a draft can be costed before it is finalized), and an empty
 * plan needs nothing — whether that is acceptable is the caller's rule.
 *
 * The catalog is read in a fixed number of batched calls, whatever the size
 * of the plan: dishes, components, products. Amounts are not rounded here;
 * rounding up to what can be bought is the shopping list's job.
 */
final readonly class MealPlanDecomposer
{
    public function __construct(private CatalogReader $catalog)
    {
    }

    /**
     * @throws MissingCatalogData when the catalog lacks a dish or product the plan reaches
     */
    public function decompose(MealPlan $plan): PlanRequirements
    {
        $dishes = [];
        $portions = [];
        foreach ($plan->entries() as $entry) {
            $key = $entry->dishId()->value;
            $dishes[$key] = $entry->dishId();
            $portions[$key] = ($portions[$key] ?? 0) + $entry->portions();
        }

        if ([] === $dishes) {
            return new PlanRequirements([], []);
        }

        $tally = new RequirementTally();
        $dishIds = array_values($dishes);
        $compositions = self::indexOrFail($this->catalog->dishCompositions($dishIds), static fn (DishComposition $d): DishId => $d->dishId, $dishIds, 'dish');
        foreach ($compositions as $key => $composition) {
            foreach ($composition->productLines as $line) {
                $tally->addProduct($line, (string) $portions[$key], 1);
            }
        }

        return new PlanRequirements($this->products($tally), []);
    }

    /**
     * @return list<ProductRequirement>
     */
    private function products(RequirementTally $tally): array
    {
        $ids = $tally->productIds();
        if ([] === $ids) {
            return [];
        }

        $infos = self::indexOrFail($this->catalog->products($ids), static fn (ProductInfo $p): Uuid => $p->productId, $ids, 'product');
        $products = array_map(static fn (ProductId $id): ProductRequirement => new ProductRequirement($id, $infos[$id->value]->name, $tally->productNeed($id)), $ids);
        usort($products, static fn (ProductRequirement $a, ProductRequirement $b): int => self::byName($a->productName, $a->productId, $b->productName, $b->productId));

        return $products;
    }

    /**
     * Case-insensitive, so "basil" does not sort after "Zucchini"; ties go
     * by id, so two products with the same name still have a fixed order.
     */
    private static function byName(string $nameA, Uuid $idA, string $nameB, Uuid $idB): int
    {
        $byName = strcmp(mb_strtolower($nameA), mb_strtolower($nameB));

        return 0 !== $byName ? $byName : strcmp($idA->value, $idB->value);
    }

    /**
     * Keys the reader's answer by id and turns "not returned" into an error:
     * the port only reports what exists, and for the decomposer every id it
     * asks for is one the plan or a recipe refers to.
     *
     * @template T of object
     *
     * @param list<T>           $found
     * @param \Closure(T): Uuid $idOf
     * @param list<Uuid>        $asked
     *
     * @return array<string, T>
     *
     * @throws MissingCatalogData
     */
    private static function indexOrFail(array $found, \Closure $idOf, array $asked, string $kind): array
    {
        $indexed = [];
        foreach ($found as $item) {
            $indexed[$idOf($item)->value] = $item;
        }

        foreach ($asked as $id) {
            if (!isset($indexed[$id->value])) {
                throw MissingCatalogData::for($kind, $id);
            }
        }

        return $indexed;
    }
}
