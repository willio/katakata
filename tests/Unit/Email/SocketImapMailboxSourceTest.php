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
    public function testItFetchesOnlyTheSelectedBoundedTextPart(): void
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
                if ($command === 'UID FETCH 7 (BODYSTRUCTURE)') {
                    return [
                        '* 7 FETCH (BODYSTRUCTURE (("TEXT" "PLAIN" ("CHARSET" "UTF-8") NIL NIL "7BIT" 5 1) '
                        . '("APPLICATION" "PDF" NIL NIL NIL "BASE64" 99999999) "MIXED"))' . "\r\n",
                        "A0004 OK fetch\r\n",
                    ];
                }
                if (str_contains($command, 'HEADER.FIELDS')) {
                    $headers = "From: reader@example.test\r\nTo: letters@example.test\r\nSubject: Latest\r\nDate: Fri, 1 Aug 2026 10:00:00 +0000\r\n";
                    return ["* 7 FETCH (BODY[HEADER.FIELDS] {" . strlen($headers) . "}\r\n", $headers, ")\r\n"];
                }
                if ($command === 'UID FETCH 7 (BODY.PEEK[1]<0.1048576>)') {
                    return ["* 7 FETCH (BODY[1]<0> {5}\r\n", 'Hello', ")\r\n"];
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
        self::assertContains('UID FETCH 7 (BODYSTRUCTURE)', $wire->commands);
        self::assertContains('UID FETCH 7 (BODY.PEEK[1]<0.1048576>)', $wire->commands);
        self::assertNotContains('UID FETCH 7 (BODY.PEEK[])', $wire->commands);
    }

    public function testItSelectsTextPlainInsideNestedMultipartAlternative(): void
    {
        $wire = new class implements ImapWireConnection {
            public function command(string $command): array
            {
                return match (true) {
                    $command === 'UID SEARCH ALL' => ["* SEARCH 9\r\n"],
                    $command === 'UID FETCH 9 (BODYSTRUCTURE)' => [
                        '* 9 FETCH (BODYSTRUCTURE ((("TEXT" "PLAIN" ("CHARSET" "UTF-8") NIL NIL "QUOTED-PRINTABLE" 12 1) '
                        . '("TEXT" "HTML" ("CHARSET" "UTF-8") NIL NIL "7BIT" 20 1) "ALTERNATIVE") '
                        . '("APPLICATION" "PDF" NIL NIL NIL "BASE64" 99999999) "MIXED"))' . "\r\n",
                    ],
                    str_contains($command, 'HEADER.FIELDS') => ["* 9 FETCH ({15}\r\n", "Subject: Nested\r\n", ")\r\n"],
                    $command === 'UID FETCH 9 (BODY.PEEK[1.1]<0.1048576>)' => ["* 9 FETCH ({12}\r\n", 'Visible=20text', ")\r\n"],
                    default => ["A0001 OK done\r\n"],
                };
            }

            public function close(): void
            {
            }
        };
        $source = new SocketImapMailboxSource(new MailTextExtractor(), static fn (): ImapWireConnection => $wire);

        $messages = $source->fetch(new ImapSettings('imap.example.test', 993, 'ssl', 'reader', 'secret', 'INBOX'));

        self::assertSame('Visible text', $messages[0]['text']);
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
