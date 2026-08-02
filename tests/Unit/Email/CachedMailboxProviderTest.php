<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use Katakata\Editorial\AtomicFile;
use Katakata\Email\Providers\CachedMailboxProvider;
use PHPUnit\Framework\TestCase;

final class CachedMailboxProviderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-mail-cache-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/messages', 0700, true);
        mkdir($this->root . '/attachments/message-1', 0700, true);

        file_put_contents($this->root . '/messages/message-1.json', json_encode([
            'id' => 'message-1',
            'from' => 'reader@example.test',
            'to' => 'letters@example.test',
            'subject' => 'A reply',
            'text' => 'Hello.',
            'html' => '<p>Hello.</p>',
            'received_at' => '2026-08-01T10:00:00+00:00',
            'attachments' => [[
                'id' => 'attachment-1',
                'name' => 'note.txt',
                'media_type' => 'text/plain',
                'bytes' => 4,
            ]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        file_put_contents($this->root . '/attachments/message-1/attachment-1', 'note');
        file_put_contents($this->root . '/index.json', json_encode([
            'messages' => ['message-1'],
            'status' => [
                'state' => 'ready',
                'last_synced_at' => '2026-08-01T10:05:00+00:00',
                'error' => null,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testItServesMessagesAndAttachmentsFromPrivateCache(): void
    {
        $provider = new CachedMailboxProvider($this->root, new AtomicFile());

        self::assertSame('ready', $provider->readiness()['status']);
        self::assertSame('2026-08-01T10:05:00+00:00', $provider->readiness()['last_synced_at']);
        self::assertCount(1, $provider->inbox());
        self::assertSame('A reply', $provider->message('message-1')?->subject);
        self::assertSame('note', $provider->attachment('message-1', 'attachment-1')?->content);
    }

    public function testReadAndArchiveStateDoNotRewriteCachedMessage(): void
    {
        $provider = new CachedMailboxProvider($this->root, new AtomicFile());
        $before = file_get_contents($this->root . '/messages/message-1.json');

        $provider->markRead('message-1', true);
        self::assertSame(0, $provider->unreadCount());

        $provider->archive('message-1');
        self::assertSame([], $provider->inbox());
        self::assertSame($before, file_get_contents($this->root . '/messages/message-1.json'));
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->remove($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }
}
