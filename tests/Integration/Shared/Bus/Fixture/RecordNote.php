<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Bus\Fixture;

use App\Shared\Application\Bus\Command;

final readonly class RecordNote implements Command
{
    public function __construct(public string $text)
    {
    }
}
