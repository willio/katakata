<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Collection;
use Katakata\Content\Post;
use Katakata\Rendering\Feed;
use Katakata\Rendering\Markdown;
use PHPUnit\Framework\TestCase;

final class FeedTest extends TestCase
{
    public function testRssContainsPublishedPostsWithAbsoluteEscapedUrls(): void
    {
        $xml = (new Feed(new Markdown()))->rss(
            new Collection([$this->post('live', 'published'), $this->post('draft', 'draft')]),
            'Katakata & Co',
            'https://example.com/',
        );

        self::assertStringContainsString('<title>Katakata &amp; Co</title>', $xml);
        self::assertStringContainsString('<link>https://example.com/2026/07/live</link>', $xml);
        self::assertStringNotContainsString('/draft</link>', $xml);
    }

    public function testJsonFeedContainsRenderedPublishedContentOnly(): void
    {
        $json = (new Feed(new Markdown()))->json(
            new Collection([$this->post('live', 'published'), $this->post('draft', 'draft')]),
            'Katakata',
            'https://example.com',
        );
        $feed = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('https://jsonfeed.org/version/1.1', $feed['version']);
        self::assertCount(1, $feed['items']);
        self::assertSame('https://example.com/2026/07/live', $feed['items'][0]['id']);
        self::assertSame('<p>Hello <strong>world</strong>.</p>', $feed['items'][0]['content_html']);
    }

    private function post(string $slug, string $status): Post
    {
        return new Post(
            slug: $slug,
            title: ucfirst($slug),
            date: new DateTimeImmutable('2026-07-28'),
            author: 'will',
            tags: ['notes'],
            excerpt: 'An excerpt.',
            status: $status,
            body: 'Hello **world**.',
            meta: [],
            path: "/content/{$slug}.md",
        );
    }
}
