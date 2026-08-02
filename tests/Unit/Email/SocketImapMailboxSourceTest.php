<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use Katakata\Email\ImapSettings;
use Katakata\Email\ImapWireConnection;
use Katakata\Email\MailTextExtractor;
use Katakata\Email\SocketImapMailboxSource;
use PHPUnit\Framework\TestCase;

final class SocketImapMailboxSourceTest extends TestCase
{
    public function testItFetchesBoundedTextOnlyMessagesThroughTheWire(): void
    {
        $wire = new class implements ImapWireConnection {
            /** @var list<string> */
            public array $commands = [];
            public bool $closed = false;

            public function command(string $command): array
            {
                $this->commands[] = $command;
                if ($command === 'UID SEARCH ALL') {
                    return ["* SEARCH 3 7\r\n", "A0003 OK search\r\n"];
                }
                if (str_starts_with($command, 'UID FETCH 7')) {
                    $raw = "From: reader@example.test\r\nTo: letters@example.test\r\nSubject: Latest\r\nDate: Fri, 1 Aug 2026 10:00:00 +0000\r\nContent-Type: text/plain\r\n\r\nHello";
                    return ["* 7 FETCH (BODY[] {" . strlen($raw) . "}\r\n", $raw, ")\r\n", "A0004 OK fetch\r\n"];
                }
                return ["A0001 OK done\r\n"];
            }

            public function close(): void
            {
                $this->closed = true;
            }
        };
        $source = new SocketImapMailboxSource(new MailTextExtractor(), static fn (): ImapWireConnection => $wire);
        $settings = new ImapSettings('imap.example.test', 993, 'ssl', 'reader', 'secret', 'INBOX');

        $messages = $source->fetch($settings, 1);

        self::assertCount(1, $messages);
        self::assertSame('uid-7', $messages[0]['id']);
        self::assertSame('Latest', $messages[0]['subject']);
        self::assertSame('Hello', $messages[0]['text']);
        self::assertSame([], $messages[0]['attachments']);
        self::assertTrue($wire->closed);
        self::assertStringStartsWith('LOGIN ', $wire->commands[0]);
        self::assertSame('SELECT "INBOX"', $wire->commands[1]);
    }

    public function testItRejectsControlCharactersBeforeConnecting(): void
    {
        $connected = false;
        $source = new SocketImapMailboxSource(
            new MailTextExtractor(),
            static function () use (&$connected): ImapWireConnection {
                $connected = true;
                throw new \RuntimeException('Should not connect.');
            },
        );
        $settings = new ImapSettings('imap.example.test', 993, 'ssl', "reader\r\nBAD", 'secret', 'INBOX');

        $this->expectException(\RuntimeException::class);
        try {
            $source->fetch($settings);
        } finally {
            self::assertFalse($connected);
        }
    }
}
