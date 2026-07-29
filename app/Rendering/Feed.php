<?php

declare(strict_types=1);

namespace Katakata\Rendering;

use Katakata\Content\Collection;
use Katakata\Content\Post;

final class Feed
{
    public function __construct(private readonly Markdown $markdown)
    {
    }

    /**
     * @param Collection<Post> $posts
     */
    public function rss(Collection $posts, string $siteName, string $siteUrl): string
    {
        $siteUrl = rtrim($siteUrl, '/');
        $items = '';

        foreach ($this->published($posts) as $post) {
            $url = $siteUrl . $post->url();
            $description = $post->excerpt ?? $this->plainText($post);
            $items .= "\n        <item>\n"
                . '            <title>' . $this->xml($post->title) . "</title>\n"
                . '            <link>' . $this->xml($url) . "</link>\n"
                . '            <guid isPermaLink="true">' . $this->xml($url) . "</guid>\n"
                . '            <pubDate>' . $post->date->format(DATE_RSS) . "</pubDate>\n"
                . '            <description>' . $this->xml($description) . "</description>\n"
                . "        </item>";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0">' . "\n"
            . "    <channel>\n"
            . '        <title>' . $this->xml($siteName) . "</title>\n"
            . '        <link>' . $this->xml($siteUrl . '/') . "</link>\n"
            . '        <description>' . $this->xml($siteName) . "</description>"
            . $items . "\n"
            . "    </channel>\n"
            . "</rss>\n";
    }

    /**
     * @param Collection<Post> $posts
     */
    public function json(Collection $posts, string $siteName, string $siteUrl): string
    {
        $siteUrl = rtrim($siteUrl, '/');
        $items = [];

        foreach ($this->published($posts) as $post) {
            $url = $siteUrl . $post->url();
            $item = [
                'id' => $url,
                'url' => $url,
                'title' => $post->title,
                'content_html' => $this->markdown->render($post->body),
                'date_published' => $post->date->format(DATE_ATOM),
            ];

            if ($post->excerpt !== null) {
                $item['summary'] = $post->excerpt;
            }

            if ($post->tags !== []) {
                $item['tags'] = $post->tags;
            }

            $items[] = $item;
        }

        return (string) json_encode([
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => $siteName,
            'home_page_url' => $siteUrl . '/',
            'feed_url' => $siteUrl . '/feed.json',
            'items' => $items,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param Collection<Post> $posts
     * @return array<int, Post>
     */
    private function published(Collection $posts): array
    {
        return array_values(array_filter(
            $posts->all(),
            static fn (Post $post): bool => $post->isPublished(),
        ));
    }

    private function plainText(Post $post): string
    {
        $text = strip_tags($this->markdown->render($post->body));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
