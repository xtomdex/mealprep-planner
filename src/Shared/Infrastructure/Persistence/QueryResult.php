<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

/**
 * Gives a DQL result the type the query promises. Doctrine returns mixed
 * for a hand-written query; checking every row here turns a mapping or query
 * mistake into an immediate error instead of a wrong type further on.
 */
final class QueryResult
{
    private function __construct()
    {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public static function listOf(string $class, mixed $result): array
    {
        if (!\is_array($result)) {
            throw new \LogicException(\sprintf('Expected a list of %s, got %s.', $class, get_debug_type($result)));
        }

        $list = [];
        foreach ($result as $row) {
            if (!$row instanceof $class) {
                throw new \LogicException(\sprintf('Expected %s, got %s.', $class, get_debug_type($row)));
            }
            $list[] = $row;
        }

        return $list;
    }
}
