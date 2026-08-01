<?php

declare(strict_types=1);

namespace Katakata\Tests\Backup;

use DateTimeImmutable;
use Katakata\Backup\BackupManager;
use PHPUnit\Framework\TestCase;

final class BackupManagerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-backup-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/content/posts', 0775, true);
        mkdir($this->root . '/storage/discussion', 0775, true);
        file_put_contents($this->root . '/content/posts/example.md', "# Example\n");
        file_put_contents($this->root . '/storage/discussion/example.json', "{}\n");
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function test_it_creates_lists_and_verifies_a_private_backup(): void
    {
        $manager = new BackupManager(
            $this->root . '/storage/backups',
            [
                'content' => $this->root . '/content',
                'storage/discussion' => $this->root . '/storage/discussion',
            ],
        );

        $created = $manager->create(new DateTimeImmutable('2026-08-01T20:30:00+07:00'));

        self::assertFileExists($created['path']);
        self::assertFileExists($created['path'] . '.sha256');
        self::assertSame(2, $created['files']);
        self::assertSame(0700, fileperms(dirname($created['path'])) & 0777);
        self::assertSame(0600, fileperms($created['path']) & 0777);
        self::assertSame(0600, fileperms($created['path'] . '.sha256') & 0777);
        self::assertTrue($manager->verify($created['path'])['valid']);

        $backups = $manager->all();
        self::assertCount(1, $backups);
        self::assertTrue($backups[0]['verified']);
    }

    public function test_verification_fails_when_archive_is_modified(): void
    {
        $manager = new BackupManager(
            $this->root . '/storage/backups',
            ['content' => $this->root . '/content'],
        );
        $created = $manager->create(new DateTimeImmutable('2026-08-01T20:31:00+07:00'));
        file_put_contents($created['path'], 'corrupt', FILE_APPEND);

        $result = $manager->verify($created['path']);

        self::assertFalse($result['valid']);
        self::assertSame('Archive checksum does not match.', $result['message']);
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($target) ? $this->remove($target) : @unlink($target);
        }

        @rmdir($path);
    }
}
