<?php

declare(strict_types=1);

namespace App\Catalog\Application\View;

use App\Shared\Application\View\QuantityView;

/**
 * One recipe line with the name of what it uses, so a client can show the
 * recipe without a second request per line.
 */
final readonly class IngredientLineView
{
    /**
     * @param string $kind product or component
     */
    public function __construct(
        public string $kind,
        public string $refId,
        public string $refName,
        public QuantityView $quantity,
    ) {
    }
}
