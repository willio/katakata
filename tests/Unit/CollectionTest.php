<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Content\Collection;
use PHPUnit\Framework\TestCase;

final class CollectionTest extends TestCase
{
    public function test_it_counts_and_iterates(): void
    {
        $collection = new Collection([1, 2, 3]);

        $this->assertCount(3, $collection);
        $this->assertSame([1, 2, 3], iterator_to_array($collection));
        $this->assertFalse($collection->isEmpty());
    }

    public function test_empty_collection(): void
    {
        $collection = new Collection();

        $this->assertTrue($collection->isEmpty());
        $this->assertNull($collection->first());
    }

    public function test_filter_returns_a_new_collection(): void
    {
        $collection = new Collection([1, 2, 3, 4]);
        $even = $collection->filter(static fn (int $n): bool => $n % 2 === 0);

        $this->assertSame([2, 4], $even->all());
        $this->assertCount(4, $collection, 'original collection is untouched');
    }

    public function test_sort_returns_a_new_ordered_collection(): void
    {
        $collection = new Collection([3, 1, 2]);
        $sorted = $collection->sort(static fn (int $a, int $b): int => $a <=> $b);

        $this->assertSame([1, 2, 3], $sorted->all());
        $this->assertSame([3, 1, 2], $collection->all(), 'original collection is untouched');
    }

    public function test_first_returns_the_first_item(): void
    {
        $collection = new Collection(['a', 'b']);

        $this->assertSame('a', $collection->first());
    }
}
