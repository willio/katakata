<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Collection;
use Katakata\Content\Draft;
use Katakata\Editorial\AtomicFile;
use Katakata\Editorial\DraftEditor;
use Katakata\Editorial\Publisher;
use Katakata\Editorial\RevisionStore;
use Katakata\Editorial\Scheduler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EditorialTest extends TestCase
{
    private string $root;
    private AtomicFile $files;
    private RevisionStore $revisions;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-editorial-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
        $this->files = new AtomicFile();
        $this->revisions = new RevisionStore($this->root . '/revisions', $this->files);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testSavingAnExistingDraftCapturesItsPreviousRevision(): void
    {
        $editor = new DraftEditor($this->root . '/drafts', $this->files, $this->revisions);
        $path = $editor->save('hello-world', 'Hello', 'First.');
        $editor->save('hello-world', 'Hello again', 'Second.');

        self::assertCount(1, $this->revisions->all('hello-world'));
        self::assertStringContainsString('First.', (string) file_get_contents($this->revisions->all('hello-world')[0]));
        self::assertStringContainsString('Second.', (string) file_get_contents($path));
    }

    public function testSchedulerReturnsOnlyDueScheduledDrafts(): void
    {
        $due = new Draft('due', 'Due', null, '', [
            'status' => 'scheduled',
            'publish_at' => '2026-07-28T08:00:00+07:00',
        ], '/tmp/due.md');
        $later = new Draft('later', 'Later', null, '', [
            'status' => 'scheduled',
            'publish_at' => '2026-07-29T08:00:00+07:00',
        ], '/tmp/later.md');

        $result = (new Scheduler())->due(
            new Collection([$due, $later]),
            new DateTimeImmutable('2026-07-28T09:00:00+07:00'),
        );

        self::assertSame(['due'], array_map(static fn (Draft $draft): string => $draft->slug, $result));
    }

    public function testPublishingWritesCanonicalPostRemovesDraftAndKeepsRevision(): void
    {
        mkdir($this->root . '/drafts', 0775, true);
        $draftPath = $this->root . '/drafts/hello.md';
        file_put_contents($draftPath, "---\ntitle: Hello\n---\n\nBody.\n");
        $draft = new Draft('hello', 'Hello', null, 'Body.', ['title' => 'Hello'], $draftPath);

        $target = (new Publisher(
            $this->root . '/posts',
            $this->files,
            $this->revisions,
        ))->publish($draft, new DateTimeImmutable('2026-07-28T10:00:00+07:00'));

        self::assertSame($this->root . '/posts/2026/07/260728_hello.md', $target);
        self::assertFileExists($target);
        self::assertFileDoesNotExist($draftPath);
        self::assertCount(1, $this->revisions->all('hello'));
    }

    public function testPublishingNeverOverwritesAnExistingPost(): void
    {
        mkdir($this->root . '/drafts', 0775, true);
        mkdir($this->root . '/posts/2026/07', 0775, true);
        $draftPath = $this->root . '/drafts/hello.md';
        file_put_contents($draftPath, 'draft');
        file_put_contents($this->root . '/posts/2026/07/260728_hello.md', 'existing');
        $draft = new Draft('hello', 'Hello', null, 'Body.', [], $draftPath);

        $this->expectException(RuntimeException::class);
        (new Publisher($this->root . '/posts', $this->files, $this->revisions))
            ->publish($draft, new DateTimeImmutable('2026-07-28'));
    }

    public function testCreatingAnExistingDraftThrowsInsteadOfOverwriting(): void
    {
        $editor = new DraftEditor($this->root . '/drafts', $this->files, $this->revisions);
        $editor->create('hello-world', 'Hello', 'First.');

        try {
            $editor->create('hello-world', 'Replacement', 'Second.');
            self::fail('Creating an existing draft must throw.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('already exists', $e->getMessage());
        }

        self::assertStringContainsString('First.', (string) file_get_contents($this->root . '/drafts/hello-world.md'));
    }

    public function testSchedulingRejectsDatesOutsideTheFilenameCentury(): void
    {
        $editor = new DraftEditor($this->root . '/drafts', $this->files, $this->revisions);
        $draft = new Draft('hello', 'Hello', null, 'Body.', [], $this->root . '/drafts/hello.md');

        $this->expectException(\InvalidArgumentException::class);
        $editor->schedule($draft, new DateTimeImmutable('1999-01-01T00:00:00+00:00'));
    }

    public function testPublishingRejectsDatesOutsideTheFilenameCentury(): void
    {
        mkdir($this->root . '/drafts', 0775, true);
        $publisher = new Publisher($this->root . '/posts', $this->files, $this->revisions);

        foreach (['1999-12-31', '2100-01-01'] as $date) {
            $draftPath = $this->root . '/drafts/hello-' . substr($date, 0, 4) . '.md';
            file_put_contents($draftPath, 'draft');
            $draft = new Draft('hello', 'Hello', null, 'Body.', [], $draftPath);

            try {
                $publisher->publish($draft, new DateTimeImmutable($date));
                self::fail("Publish date [{$date}] must be rejected.");
            } catch (RuntimeException $e) {
                self::assertStringContainsString('2000-2099', $e->getMessage());
            }
        }
    }

    public function testPublishingStripsTheDerivedFirstLineTitle(): void
    {
        mkdir($this->root . '/drafts', 0775, true);
        $draftPath = $this->root . '/drafts/hello.md';
        file_put_contents($draftPath, 'draft');
        $draft = new Draft('hello', 'Duplication Check', null, "Duplication Check\n\nReal body text.", [], $draftPath);

        $target = (new Publisher($this->root . '/posts', $this->files, $this->revisions))
            ->publish($draft, new DateTimeImmutable('2026-07-28'));

        $written = (string) file_get_contents($target);
        self::assertStringContainsString('Real body text.', $written);
        self::assertSame(1, substr_count($written, 'Duplication Check'), 'Only the front-matter title may remain.');
    }

    public function testPublishingStripsAHeadingStyleDerivedTitle(): void
    {
        mkdir($this->root . '/drafts', 0775, true);
        $draftPath = $this->root . '/drafts/hello.md';
        file_put_contents($draftPath, 'draft');
        $draft = new Draft('hello', 'Hello', null, "# Hello\n\nBody.", [], $draftPath);

        $target = (new Publisher($this->root . '/posts', $this->files, $this->revisions))
            ->publish($draft, new DateTimeImmutable('2026-07-28'));

        self::assertStringNotContainsString('# Hello', (string) file_get_contents($target));
    }

    public function testPublishingKeepsAFirstLineThatDoesNotMatchTheTitle(): void
    {
        mkdir($this->root . '/drafts', 0775, true);
        $draftPath = $this->root . '/drafts/hello.md';
        file_put_contents($draftPath, 'draft');
        $draft = new Draft('hello', 'Hello', null, "A deliberate opening line.\n\nBody.", [], $draftPath);

        $target = (new Publisher($this->root . '/posts', $this->files, $this->revisions))
            ->publish($draft, new DateTimeImmutable('2026-07-28'));

        self::assertStringContainsString('A deliberate opening line.', (string) file_get_contents($target));
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $target = $path . '/' . $entry;
            is_dir($target) ? $this->remove($target) : unlink($target);
        }

        rmdir($path);
    }
}
