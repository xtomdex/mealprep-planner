<?php

declare(strict_types=1);

namespace App\Tests\Support\Shared;

use Psr\Log\AbstractLogger;

/**
 * Keeps log records in memory, so a test can check what was logged without
 * reading Monolog's files.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    private array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<array{level: mixed, message: string, context: array<mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }
}
