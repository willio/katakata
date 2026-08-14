<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Collection;
use Katakata\Content\Post;
use Katakata\Rendering\Archive;
use PHPUnit\Framework\TestCase;

final class ArchiveMalformedFilterTest extends TestCase
{
    public function testNonScalarYearAndMonthFiltersAreIgnored(): void
    {
        $posts = new Collection([
            new Post('june', 'June', new DateTimeImmutable('2018-06-27'), null, [], null, 'published', '', [], '/content/june.md'),
            new Post('may', 'May', new DateTimeImmutable('2018-05-31'), null, [], null, 'published', '', [], '/content/may.md'),
        ]);

        $years = (new Archive())->years($posts, '', ['2018'], ['06']);

        self::assertSame([2018], array_keys($years));
        self::assertCount(2, $years['2018']);
    }
}
