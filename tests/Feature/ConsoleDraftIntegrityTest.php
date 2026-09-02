<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use FilesystemIterator;
use Katakata\Application as Kernel;
use Katakata\Console\Application as ConsoleApplication;
use Katakata\Content\Repository;
use Katakata\Editorial\AtomicFile;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\Publisher;
use Katakata\Editorial\RevisionStore;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ConsoleDraftIntegrityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-console-draft-' . bin2hex(random_bytes(6));
        foreach (['content/posts', 'content/drafts', 'content/authors', 'content/assets'] as $path) {
            mkdir($this->root . '/' . $path, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function testDraftCreateRefusesToOverwriteAnExistingDraft(): void
    {
        $console = $this->console();

        self::assertSame(0, $console->run(['draft:create', 'hello', 'First']));
        self::assertSame(1, $console->run(['draft:create', 'hello', 'Duplicate Title']));
        self::assertStringContainsString(
            'First',
            (string) file_get_contents($this->root . '/content/drafts/hello.md'),
        );
    }

    public function testDraftScheduleRejectsDatesOutsideTheFilenameCentury(): void
    {
        $console = $this->console();
        self::assertSame(0, $console->run(['draft:create', 'hello', 'Hello']));

        self::assertSame(1, $console->run(['draft:schedule', 'hello', '1999-01-01']));
        self::assertStringNotContainsString(
            'status: scheduled',
            (string) file_get_contents($this->root . '/content/drafts/hello.md'),
        );
    }

    public function testDraftPublishRejectsDatesOutsideTheFilenameCentury(): void
    {
        $console = $this->console();
        self::assertSame(0, $console->run(['draft:create', 'hello', 'Hello']));

        self::assertSame(1, $console->run(['draft:publish', 'hello', '2100-01-01']));
        self::assertFileExists($this->root . '/content/drafts/hello.md');
        self::assertSame([], glob($this->root . '/content/posts/*/*/*.md') ?: []);
    }

    private function console(): ConsoleApplication
    {
        $app = new Kernel($this->root);
        $app->config()->set('content', [
            'posts_path' => 'content/posts',
            'drafts_path' => 'content/drafts',
            'authors_path' => 'content/authors',
            'assets_path' => 'content/assets',
        ]);
        $app->config()->freeze();

        $files = new AtomicFile();
        $revisions = new RevisionStore($this->root . '/content/revisions', $files);
        $app->instance(Repository::class, Repository::forApplication($app));
        $app->instance(DraftEditor::class, new DraftEditor($this->root . '/content/drafts', $files, $revisions));
        $app->instance(Publisher::class, new Publisher($this->root . '/content/posts', $files, $revisions));

        return new ConsoleApplication($app);
    }
}
