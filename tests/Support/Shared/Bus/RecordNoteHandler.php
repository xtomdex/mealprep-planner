<?php

declare(strict_types=1);

namespace App\Tests\Support\Shared\Bus;

use App\Shared\Application\Bus\CommandHandler;

/**
 * Keeps what it received, so the test can see that the command arrived.
 */
final class RecordNoteHandler implements CommandHandler
{
    /** @var list<string> */
    public array $notes = [];

    public function __invoke(RecordNote $command): void
    {
        $this->notes[] = $command->text;
    }
}
