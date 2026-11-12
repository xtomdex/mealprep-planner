<?php

declare(strict_types=1);

namespace App\Planning\Domain\Model;

/**
 * A plan is edited while it is a draft and frozen once finalized. Two states
 * only: nothing in this demo needs "archived" or "cancelled", and an enum
 * that grows later is cheaper than states nobody uses.
 */
enum MealPlanStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
}
