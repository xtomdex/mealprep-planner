<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

/**
 * What an ingredient line points at. A dish is absent on purpose: a dish is
 * the end of the chain and is never an ingredient of anything.
 */
enum RefKind: string
{
    case Product = 'product';
    case Component = 'component';
}
