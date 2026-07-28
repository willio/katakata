<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use Katakata\Content\Post;
use Katakata\Editorial\AtomicFile;
use Katakata\Rendering\Markdown;

final class NewsletterAdapter implements Adapter
{
    public function __construct(
        private readonly string $outboxPath,
        private readonly string $appUrl,
        private readonly Markdown $markdown,
        private readonly AtomicFile $files,
    ) {
    }

    public function channel(): string
    {
        return 'newsletter';
    }

    /** @return array{path: string, canonical_url: string} */
    public function distribute(Post $post): array
    {
        $canonicalUrl = rtrim($this->appUrl, '/') . $post->url();
        $bodyHtml = $this->markdown->render($post->body);
        $payload = [
            'version' => 1,
            'channel' => $this->channel(),
            'post_slug' => $post->slug,
            'subject' => $post->title,
            'canonical_url' => $canonicalUrl,
            'published_at' => $post->date->format(DATE_ATOM),
            'html' => $bodyHtml . sprintf(
                "\n<p><a href=\"%s\">Read on the web</a></p>",
                htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'),
            ),
            'text' => trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                . "\n\nRead on the web: {$canonicalUrl}\n",
        ];
        $path = $this->outboxPath . '/' . $post->date->format('Y/m') . '/' . $post->slug . '.json';
        $this->files->write(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );

        return ['path' => $path, 'canonical_url' => $canonicalUrl];
    }
}
