<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use DateTimeImmutable;
use Katakata\Editorial\AtomicFile;
use Katakata\Email\MailboxRefreshRequest;
use PHPUnit\Framework\TestCase;

final class MailboxRefreshRequestTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/katakata-mail-refresh-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            array_map('unlink', glob($this->directory . '/*') ?: []);
            rmdir($this->directory);
        }
    }

    public function testItRecordsOnePrivateRefreshRequestUntilTheWorkerConsumesIt(): void
    {
        $path = $this->directory . '/refresh-request.json';
        $request = new MailboxRefreshRequest($path, new AtomicFile());
        $at = new DateTimeImmutable('2026-08-02T12:00:00+00:00');

        $request->request($at);

        self::assertFileExists($path);
        self::assertSame(['requested_at' => $at->format(DATE_ATOM)], json_decode((string) file_get_contents($path), true));
        self::assertTrue($request->consume());
        self::assertFileDoesNotExist($path);
        self::assertFalse($request->consume());
    }
}
