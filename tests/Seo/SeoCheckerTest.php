<?php

declare(strict_types=1);

namespace Katakata\Tests\Seo;

use DateTimeImmutable;
use Katakata\Content\Repository;
use Katakata\Seo\SeoChecker;
use PHPUnit\Framework\TestCase;

final class SeoCheckerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-seo-' . bin2hex(random_bytes(6));
        foreach (['posts/2026/07', 'drafts', 'authors', 'assets', 'public'] as $path) {
            mkdir($this->root . '/' . $path, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function test_it_reports_reproducible_content_and_public_file_issues(): void
    {
        file_put_contents($this->root . '/posts/2026/07/260728_first.md', <<<MD
---
title: First
date: 2026-07-28
excerpt: Too short
---
Read [missing](/2026/07/missing).
MD);
        $summary = $this->checker()->check(new DateTimeImmutable('2026-07-28T00:00:00Z'));
        $types = array_map(static fn ($issue): string => $issue->type, $summary->issues);

        self::assertContains('excerpt_short', $types);
        self::assertContains('internal_link_broken', $types);
        self::assertSame(2, count(array_filter($types, static fn (string $type): bool => $type === 'public_file_missing')));
    }

    public function test_it_passes_when_post_metadata_links_and_public_files_are_sound(): void
    {
        file_put_contents($this->root . '/posts/2026/07/260728_first.md', <<<MD
---
title: First
date: 2026-07-28
excerpt: This excerpt is intentionally long enough to satisfy the basic search description check.
---
No internal links.
MD);
        file_put_contents($this->root . '/public/sitemap.xml', '<urlset/>');
        file_put_contents($this->root . '/public/robots.txt', "User-agent: *\nAllow: /\n");

        self::assertTrue($this->checker()->check()->passed());
    }

    private function checker(): SeoChecker
    {
        return new SeoChecker(
            new Repository(
                $this->root . '/posts',
                $this->root . '/drafts',
                $this->root . '/authors',
                $this->root . '/assets',
            ),
            $this->root . '/public',
        );
    }
}
