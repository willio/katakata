<?php

declare(strict_types=1);

namespace Katakata\Mail;

use Katakata\Content\Post;
use Katakata\Content\Repository;
use Katakata\Distribution\SubscriberStore;
use Katakata\Rendering\Markdown;

final class MailWorkspace
{
    public function __construct(
        private readonly Repository $content,
        private readonly SubscriberStore $subscribers,
        private readonly Markdown $markdown,
        private readonly string $appUrl,
    ) {
    }

    /** @return list<array{slug:string,title:string,published_at:string,author:?string,excerpt:?string,url:string}> */
    public function reviewQueue(): array
    {
        $posts = array_filter(
            $this->content->posts()->all(),
            static fn (Post $post): bool => $post->isPublished() && self::newsletterIntent($post),
        );

        usort($posts, static fn (Post $left, Post $right): int => $right->date <=> $left->date);

        return array_map(
            static fn (Post $post): array => [
                'slug' => $post->slug,
                'title' => $post->title,
                'published_at' => $post->date->format(DATE_ATOM),
                'author' => $post->author,
                'excerpt' => $post->excerpt,
                'url' => $post->url(),
            ],
            array_values($posts),
        );
    }

    /** @return array{count:int,recipients:list<array{email:string,confirmed_at:?string}>} */
    public function recipientPreview(): array
    {
        $recipients = array_map(
            static fn (array $subscriber): array => [
                'email' => (string) $subscriber['email'],
                'confirmed_at' => isset($subscriber['confirmed_at']) ? (string) $subscriber['confirmed_at'] : null,
            ],
            $this->subscribers->active(),
        );

        return ['count' => count($recipients), 'recipients' => $recipients];
    }

    /** @return array{post:array{slug:string,title:string,published_at:string,author:?string,excerpt:?string,url:string},recipient_count:int}|null */
    public function campaignPreview(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        foreach ($this->reviewQueue() as $candidate) {
            if ($candidate['slug'] === $slug) {
                return ['post' => $candidate, 'recipient_count' => $this->recipientPreview()['count']];
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    public function dispatchProof(string $slug): ?array
    {
        $campaign = $this->campaignPreview($slug);
        if ($campaign === null) {
            return null;
        }

        $post = $this->content->findPost($slug);
        if ($post === null || !$post->isPublished() || !self::newsletterIntent($post)) {
            return null;
        }

        $audience = $this->recipientPreview();
        $canonicalUrl = rtrim($this->appUrl, '/') . $post->url();
        $bodyHtml = $this->markdown->render($post->body);
        $html = $bodyHtml . sprintf(
            "\n<p><a href=\"%s\">Read on the web</a></p>",
            htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'),
        );
        $text = trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            . "\n\nRead on the web: {$canonicalUrl}\n";
        $preheader = trim((string) ($post->meta['preheader'] ?? $post->excerpt ?? ''));

        $warnings = [];
        if (mb_strlen($post->title) > 78) {
            $warnings[] = 'Subject exceeds 78 characters.';
        }
        if ($preheader === '') {
            $warnings[] = 'Preheader is missing.';
        }
        if (trim((string) $post->excerpt) === '') {
            $warnings[] = 'Excerpt is missing.';
        }
        if ($audience['count'] === 0) {
            $warnings[] = 'No confirmed recipients are eligible.';
        }

        return [
            'post' => $campaign['post'],
            'subject' => $post->title,
            'preheader' => $preheader,
            'canonical_url' => $canonicalUrl,
            'recipient_count' => $audience['count'],
            'recipients' => $audience['recipients'],
            'html' => $html,
            'text' => $text,
            'estimated_bytes' => strlen($html) + strlen($text),
            'warnings' => $warnings,
        ];
    }

    private static function newsletterIntent(Post $post): bool
    {
        $value = $post->meta['publish_as_newsletter'] ?? false;
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
