<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Bus\Fixture;

use App\Shared\Application\Bus\Query;

final readonly class FindNote implements Query
{
    public function __construct(public string $id)
    {
    }
}
