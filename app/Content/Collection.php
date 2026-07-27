<?php

declare(strict_types=1);

namespace Katakata\Content;

use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A small, read-only collection of content objects.
 *
 * @template T
 * @implements IteratorAggregate<int, T>
 */
final class Collection implements IteratorAggregate, Countable
{
    /** @var array<int, T> */
    private readonly array $items;

    /**
     * @param array<int, T> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array<int, T>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @param Closure(T): bool $callback
     * @return self<T>
     */
    public function filter(Closure $callback): self
    {
        return new self(array_values(array_filter($this->items, $callback)));
    }

    /**
     * @param Closure(T, T): int $comparator
     * @return self<T>
     */
    public function sort(Closure $comparator): self
    {
        $items = $this->items;
        usort($items, $comparator);

        return new self($items);
    }

    /**
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }
}
