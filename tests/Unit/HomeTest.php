<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Collection;
use Katakata\Content\Post;
use Katakata\Rendering\Home;
use PHPUnit\Framework\TestCase;

final class HomeTest extends TestCase
{
    public function testItReturnsOnlyTheLatestPublishedPosts(): void
    {
        $posts = new Collection([
            $this->post('older', '2025-12-31', 'published'),
            $this->post('draft', '2027-01-01', 'draft'),
            $this->post('newest', '2026-07-30', 'published'),
            $this->post('middle', '2026-01-15', 'published'),
        ]);

        self::assertSame(
            ['newest', 'middle'],
            array_map(
                static fn (Post $post): string => $post->slug,
                (new Home())->latest($posts, 2),
            ),
        );
    }

    public function testItReturnsAnEmptyListForAZeroLimit(): void
    {
        self::assertSame([], (new Home())->latest(new Collection([
            $this->post('published', '2026-07-30', 'published'),
        ]), 0));
    }

    public function testItPreparesTheEditorialHomepageGroupsAcrossYearBoundaries(): void
    {
        $posts = new Collection([
            $this->post('older-edition', '2025-12-31', 'published'),
            $this->post('may', '2026-05-02', 'published'),
            $this->post('june-two', '2026-06-08', 'published'),
            $this->post('june-one', '2026-06-22', 'published'),
            $this->post('recent-six', '2026-07-03', 'published'),
            $this->post('recent-five', '2026-07-12', 'published'),
            $this->post('recent-four', '2026-07-21', 'published'),
            $this->post('recent-three', '2026-07-29', 'published'),
            $this->post('recent-two', '2026-08-04', 'published'),
            $this->post('recent-one', '2026-08-08', 'published'),
            $this->post('lead', '2026-08-10', 'published'),
        ]);

        $layout = (new Home())->layout($posts);

        self::assertSame('lead', $layout['lead']?->slug);
        self::assertSame(['2026-08', '2026-07', '2026-06', '2026-05', '2025-12'], array_keys($layout['months']));
    }

    public function testItBuildsBoundedWeeklyRotatingMonthShelves(): void
    {
        $posts = [$this->post('lead', '2026-08-10', 'published')];
        foreach ([['july', '2026-07', 13], ['june', '2026-06', 10], ['may', '2026-05', 7], ['april', '2026-04', 4]] as [$prefix, $month, $count]) {
            for ($index = 1; $index <= $count; $index++) {
                $posts[] = $this->post("{$prefix}-{$index}", "{$month}-" . str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'published');
            }
        }

        $layout = (new Home())->layout(new Collection($posts), new DateTimeImmutable('2026-08-10T12:00:00+07:00'));

        self::assertSame('lead', $layout['lead']?->slug);
        self::assertArrayHasKey('months', $layout);
        self::assertSame(['2026-07', '2026-06', '2026-05', '2026-04'], array_keys($layout['months']));
        self::assertContains(count($layout['months']['2026-07']['posts']), [1, 12]);
        self::assertLessThanOrEqual(9, count($layout['months']['2026-06']['posts']));
        self::assertLessThanOrEqual(6, count($layout['months']['2026-05']['posts']));
        self::assertLessThanOrEqual(3, count($layout['months']['2026-04']['posts']));
        self::assertTrue($layout['months']['2026-07']['has_more']);
        self::assertSame('/archive?year=2026&month=07', $layout['months']['2026-07']['browse_url']);

        $nextWeek = (new Home())->layout(new Collection($posts), new DateTimeImmutable('2026-08-17T12:00:00+07:00'));
        self::assertSame(13, count(array_unique(array_merge(
            array_map(static fn (Post $post): string => $post->slug, $layout['months']['2026-07']['posts']),
            array_map(static fn (Post $post): string => $post->slug, $nextWeek['months']['2026-07']['posts']),
        ))));
    }

    private function post(string $slug, string $date, string $status): Post
    {
        return new Post(
            slug: $slug,
            title: ucfirst($slug),
            date: new DateTimeImmutable($date),
            author: null,
            tags: [],
            excerpt: null,
            status: $status,
            body: '',
            meta: [],
            path: "/tmp/{$slug}.md",
        );
    }
}
