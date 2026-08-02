<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use Katakata\Email\ImapWireConnection;
use Katakata\Email\TlsImapWireConnection;
use PHPUnit\Framework\TestCase;

final class ImapWireConnectionTest extends TestCase
{
    public function testTlsWireImplementsTheBoundedConnectionContract(): void
    {
        self::assertTrue(is_subclass_of(TlsImapWireConnection::class, ImapWireConnection::class));

        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Email/TlsImapWireConnection.php');
        self::assertIsString($source);
        self::assertStringContainsString("stream_socket_client(", $source);
        self::assertStringContainsString("'verify_peer' => true", $source);
        self::assertStringContainsString("'verify_peer_name' => true", $source);
        self::assertStringContainsString('stream_set_timeout', $source);
        self::assertStringContainsString('contains invalid control characters', $source);
        self::assertStringNotContainsString('imap_open(', $source);
    }
}
