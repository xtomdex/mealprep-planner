<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Marker for a request to read state without changing it. Its handler
 * returns a result; asking the same query twice changes nothing.
 */
interface Query
{
}
