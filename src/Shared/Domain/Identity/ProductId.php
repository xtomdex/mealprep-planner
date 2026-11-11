<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identity;

/**
 * Identifies a product: something that is bought (chicken breast, rice).
 *
 * The catalog owns the thing itself, but planning refers to it too, and
 * modules may share nothing except the shared kernel; so the id lives here.
 */
final readonly class ProductId extends Uuid
{
}
