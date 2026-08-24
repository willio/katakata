<?php

declare(strict_types=1);

namespace Tests\Unit\Editorial;

use Katakata\Content\Draft;
use Katakata\Editorial\AtomicFile;
use Katakata\Editorial\ContentTrash;
use Katakata\Editorial\RevisionStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentTrashTest extends TestCase
{
    private string $root;
    private ContentTrash $trash;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-trash-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/content/drafts', 0775, true);
        mkdir($this->root . '/content/posts', 0775, true);
        $files = new AtomicFile();
        $this->trash = new ContentTrash(
            $this->root . '/content',
            $files,
            new RevisionStore($this->root . '/content/revisions', $files),
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            exec('rm -rf ' . escapeshellarg($this->root));
        }
    }

    public function testTrashAndRestorePreserveExactDraftBytes(): void
    {
        $source = $this->root . '/content/drafts/example.md';
        $bytes = "---\ntitle: Example\n---\nExact bytes.\n";
        file_put_contents($source, $bytes);
        $draft = new Draft('example', 'Example', null, 'Exact bytes.', [], $source);

        $item = $this->trash->trashDraft($draft, 'editor-1');

        self::assertFileDoesNotExist($source);
        self::assertSame($bytes, file_get_contents($this->root . '/content/trash/drafts/' . $item->id . '.md'));
        self::assertCount(1, glob($this->root . '/content/revisions/example/*.md') ?: []);

        self::assertSame($source, $this->trash->restore('draft', $item->id));
        self::assertSame($bytes, file_get_contents($source));
        self::assertSame([], $this->trash->all());
    }

    public function testRestoreRefusesCollisionAndKeepsTrashCopy(): void
    {
        $source = $this->root . '/content/drafts/example.md';
        file_put_contents($source, 'original');
        $item = $this->trash->trashDraft(new Draft('example', 'Example', null, '', [], $source), 'editor-1');
        file_put_contents($source, 'replacement');

        $this->expectException(RuntimeException::class);
        try {
            $this->trash->restore('draft', $item->id);
        } finally {
            self::assertFileExists($this->root . '/content/trash/drafts/' . $item->id . '.md');
            self::assertSame('replacement', file_get_contents($source));
        }
    }

    public function testChecksumMismatchBlocksRestoreAndPurge(): void
    {
        $source = $this->root . '/content/drafts/example.md';
        file_put_contents($source, 'original');
        $item = $this->trash->trashDraft(new Draft('example', 'Example', null, '', [], $source), 'editor-1');
        file_put_contents($this->root . '/content/trash/drafts/' . $item->id . '.md', 'tampered');

        $this->expectException(RuntimeException::class);
        $this->trash->purge('draft', $item->id, $item->id);
    }

    public function testPurgeRequiresExactRepeatedId(): void
    {
        $source = $this->root . '/content/drafts/example.md';
        file_put_contents($source, 'original');
        $item = $this->trash->trashDraft(new Draft('example', 'Example', null, '', [], $source), 'editor-1');

        $this->expectException(RuntimeException::class);
        $this->trash->purge('draft', $item->id, 'wrong');
    }
}
