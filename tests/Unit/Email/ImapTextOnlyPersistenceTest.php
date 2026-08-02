<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\ImapMailboxSource;
use Katakata\Email\ImapSettings;
use Katakata\Email\ImapSynchronizer;
use PHPUnit\Framework\TestCase;

final class ImapTextOnlyPersistenceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-imap-text-only-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testSynchronizerDropsSourceProvidedHtmlAtPersistenceBoundary(): void
    {
        $source = new class implements ImapMailboxSource {
            public function fetch(ImapSettings $settings, int $limit = 100): array
            {
                return [[
                    'id' => 'uid-300',
                    'from' => 'reader@example.test',
                    'to' => 'letters@example.test',
                    'subject' => 'Text only',
                    'text' => 'Visible text.',
                    'html' => '<img src="tracking.example/pixel">',
                    'received_at' => '2026-08-02T10:00:00+00:00',
                    'attachments' => [],
                ]];
            }
        };
        $settings = new ImapSettings('imap.example.test', 993, 'ssl', 'reader', 'test-password', 'INBOX');
        $sync = new ImapSynchronizer($settings, $source, $this->root, new AtomicFile());

        $sync->sync(10, new DateTimeImmutable('2026-08-02T11:00:00+00:00'));

        $cached = json_decode(
            (string) file_get_contents($this->root . '/messages/uid-300.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame('Visible text.', $cached['text']);
        self::assertArrayNotHasKey('html', $cached);
        self::assertStringNotContainsString('tracking.example', json_encode($cached, JSON_THROW_ON_ERROR));
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
