<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\Draft;
use Katakata\Email\DraftConflict;
use Katakata\Email\FileDraftStore;
use PHPUnit\Framework\TestCase;

final class FileDraftStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/katakata-drafts-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path . '/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->path . '/.*') ?: [] as $file) {
            if (!in_array(basename($file), ['.', '..'], true)) @unlink($file);
        }
        @rmdir($this->path);
    }

    public function testItCreatesAndVersionsDrafts(): void
    {
        $store = new FileDraftStore($this->path, new AtomicFile());
        $createdAt = new DateTimeImmutable('2026-08-02T10:00:00+00:00');
        $draft = new Draft(str_repeat('a', 32), 'reader@example.test', 'Subject', 'First', null, 1, $createdAt, $createdAt);

        $created = $store->create($draft);
        $saved = $store->save(new Draft($draft->id, $draft->to, $draft->subject, 'Second', null, 1, $createdAt, $createdAt->modify('+1 minute')), 1);

        self::assertSame(1, $created->version);
        self::assertSame(2, $saved->version);
        self::assertSame('Second', $store->find($draft->id)?->text);
    }

    public function testItRejectsAStaleVersionWithCurrentRecoveryData(): void
    {
        $store = new FileDraftStore($this->path, new AtomicFile());
        $now = new DateTimeImmutable('2026-08-02T10:00:00+00:00');
        $draft = new Draft(str_repeat('b', 32), '', '', 'First', null, 1, $now, $now);
        $store->create($draft);
        $store->save(new Draft($draft->id, '', '', 'Second', null, 1, $now, $now), 1);

        try {
            $store->save(new Draft($draft->id, '', '', 'Stale', null, 1, $now, $now), 1);
            self::fail('Expected a draft conflict.');
        } catch (DraftConflict $conflict) {
            self::assertSame(2, $conflict->current->version);
            self::assertSame('Second', $conflict->current->text);
        }
    }

    public function testItDeletesOnlyTheExpectedVersion(): void
    {
        $store = new FileDraftStore($this->path, new AtomicFile());
        $now = new DateTimeImmutable('2026-08-02T10:00:00+00:00');
        $draft = new Draft(str_repeat('d', 32), '', '', 'First', null, 1, $now, $now);
        $store->create($draft);
        $saved = $store->save(new Draft($draft->id, '', '', 'Newer', null, 1, $now, $now), 1);

        self::assertFalse($store->deleteIfVersion($draft->id, 1));
        self::assertSame('Newer', $store->find($draft->id)?->text);
        self::assertTrue($store->deleteIfVersion($draft->id, $saved->version));
        self::assertNull($store->find($draft->id));
    }

    public function testItDecodesLegacyDraftsAsVersionOne(): void
    {
        mkdir($this->path, 0700, true);
        $id = str_repeat('c', 32);
        file_put_contents($this->path . '/' . $id . '.json', json_encode([
            'id' => $id,
            'to' => 'reader@example.test',
            'subject' => 'Legacy',
            'text' => 'Body',
            'updated_at' => '2026-08-02T10:00:00+00:00',
        ], JSON_THROW_ON_ERROR));

        $draft = (new FileDraftStore($this->path, new AtomicFile()))->find($id);

        self::assertSame(1, $draft?->version);
        self::assertSame($draft?->updatedAt->format(DATE_ATOM), $draft?->createdAt->format(DATE_ATOM));
    }
}
