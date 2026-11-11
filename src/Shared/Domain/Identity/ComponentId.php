<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identity;

/**
 * Identifies a component: something cooked from products and other components.
 *
 * The catalog owns the thing itself, but planning refers to it too, and
 * modules may share nothing except the shared kernel; so the id lives here.
 */
final readonly class ComponentId extends Uuid
{
}
