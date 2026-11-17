<?php

declare(strict_types=1);

namespace App\Planning\Domain\Exception;

/**
 * The catalog answered with data that its own rules should have made
 * impossible: a referenced id that does not exist, nesting deeper than
 * allowed. That is a bug or corrupted data, not a request the client can
 * fix, so this family stays outside the shared DomainException hierarchy
 * (which outer layers turn into 4xx answers) and surfaces as a server error.
 */
abstract class CatalogIntegrityViolation extends \LogicException
{
}
