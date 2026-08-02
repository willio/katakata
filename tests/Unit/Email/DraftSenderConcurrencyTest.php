<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\Draft;
use Katakata\Email\DraftSender;
use Katakata\Email\FileDraftStore;
use Katakata\Email\OutboundMailProvider;
use PHPUnit\Framework\TestCase;

final class DraftSenderConcurrencyTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/katakata-draft-send-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path . '/*') ?: [] as $file) @unlink($file);
        foreach (glob($this->path . '/.*') ?: [] as $file) {
            if (!in_array(basename($file), ['.', '..'], true)) @unlink($file);
        }
        @rmdir($this->path);
    }

    public function testNewerAutosaveIsNotDeletedAfterOlderVersionSends(): void
    {
        $store = new FileDraftStore($this->path, new AtomicFile());
        $now = new DateTimeImmutable('2026-08-02T10:00:00+00:00');
        $draft = new Draft(str_repeat('e', 32), 'reader@example.test', 'Subject', 'Original', null, 1, $now, $now);
        $store->create($draft);

        $outbound = new class($store) implements OutboundMailProvider {
            public function __construct(private readonly FileDraftStore $store) {}

            public function send(Draft $draft): void
            {
                $this->store->save(new Draft(
                    $draft->id,
                    $draft->to,
                    $draft->subject,
                    'Newer autosave',
                    $draft->inReplyTo,
                    $draft->version,
                    $draft->createdAt,
                    $draft->updatedAt->modify('+1 minute'),
                ), $draft->version);
            }
        };

        (new DraftSender($store, $outbound))->send($draft->id);

        $remaining = $store->find($draft->id);
        self::assertNotNull($remaining);
        self::assertSame(2, $remaining->version);
        self::assertSame('Newer autosave', $remaining->text);
    }
}
