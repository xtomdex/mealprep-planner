<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identity;

/**
 * Identifies a dish: one portion of something to eat.
 *
 * The catalog owns the thing itself, but planning refers to it too, and
 * modules may share nothing except the shared kernel; so the id lives here.
 */
final readonly class DishId extends Uuid
{
}
