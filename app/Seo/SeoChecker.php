<?php

declare(strict_types=1);

namespace Katakata\Seo;

use DateTimeImmutable;
use Katakata\Content\Post;
use Katakata\Content\Repository;

final class SeoChecker
{
    public function __construct(
        private readonly Repository $repository,
        private readonly string $publicPath,
    ) {
    }

    public function check(?DateTimeImmutable $checkedAt = null): SeoCheckSummary
    {
        $posts = $this->repository->posts()->all();
        $issues = [
            ...$this->excerptIssues($posts),
            ...$this->duplicateSlugIssues($posts),
            ...$this->brokenLinkIssues($posts),
            ...$this->publicFileIssues(),
        ];

        return new SeoCheckSummary($checkedAt ?? new DateTimeImmutable('now'), $issues);
    }

    /** @param array<int, Post> $posts @return array<int, SeoIssue> */
    private function excerptIssues(array $posts): array
    {
        $issues = [];
        foreach ($posts as $post) {
            $length = $post->excerpt === null ? 0 : $this->length(trim($post->excerpt));
            if ($length === 0) {
                $issues[] = new SeoIssue($post->slug, 'excerpt_missing', 'Excerpt is missing.');
            } elseif ($length < 50) {
                $issues[] = new SeoIssue($post->slug, 'excerpt_short', "Excerpt is {$length} characters; use at least 50.");
            } elseif ($length > 160) {
                $issues[] = new SeoIssue($post->slug, 'excerpt_long', "Excerpt is {$length} characters; keep it within 160.");
            }
        }

        return $issues;
    }

    /** @param array<int, Post> $posts @return array<int, SeoIssue> */
    private function duplicateSlugIssues(array $posts): array
    {
        $counts = [];
        foreach ($posts as $post) {
            $counts[$post->slug] = ($counts[$post->slug] ?? 0) + 1;
        }

        $issues = [];
        foreach ($counts as $slug => $count) {
            if ($count > 1) {
                $issues[] = new SeoIssue($slug, 'slug_duplicate', "Slug is used by {$count} posts.");
            }
        }

        return $issues;
    }

    /** @param array<int, Post> $posts @return array<int, SeoIssue> */
    private function brokenLinkIssues(array $posts): array
    {
        $urls = [];
        foreach ($posts as $post) {
            $urls[$post->url()] = true;
        }

        $issues = [];
        foreach ($posts as $post) {
            preg_match_all('#(?<![A-Za-z0-9])(/\d{4}/\d{2}/[a-z0-9]+(?:-[a-z0-9]+)*)#', $post->body, $matches);
            foreach (array_unique($matches[1] ?? []) as $url) {
                if (!isset($urls[$url])) {
                    $issues[] = new SeoIssue($post->slug, 'internal_link_broken', "Internal link [{$url}] does not resolve.");
                }
            }
        }

        return $issues;
    }

    /** @return array<int, SeoIssue> */
    private function publicFileIssues(): array
    {
        $issues = [];
        foreach (['sitemap.xml', 'robots.txt'] as $filename) {
            if (!is_file($this->publicPath . DIRECTORY_SEPARATOR . $filename)) {
                $issues[] = new SeoIssue('site', 'public_file_missing', "Public file [{$filename}] is missing.");
            }
        }

        return $issues;
    }

    private function length(string $value): int
    {
        $matched = preg_match_all('/./us', $value, $characters);

        return $matched === false ? strlen($value) : $matched;
    }
}
