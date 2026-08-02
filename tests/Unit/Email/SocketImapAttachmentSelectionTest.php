<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use Katakata\Email\ImapSettings;
use Katakata\Email\ImapWireConnection;
use Katakata\Email\MailTextExtractor;
use Katakata\Email\SocketImapMailboxSource;
use PHPUnit\Framework\TestCase;

final class SocketImapAttachmentSelectionTest extends TestCase
{
    public function testTextAttachmentBeforeBodyIsNeverFetched(): void
    {
        $wire = new class implements ImapWireConnection {
            public array $commands = [];

            public function command(string $command): array
            {
                $this->commands[] = $command;
                if ($command === 'UID SEARCH ALL') {
                    return ["* SEARCH 12\r\n"];
                }
                if ($command === 'UID FETCH 12 (BODYSTRUCTURE)') {
                    return [
                        '* 12 FETCH (BODYSTRUCTURE (("TEXT" "PLAIN" ("NAME" "notes.txt") NIL NIL "7BIT" 17 1 NIL ("ATTACHMENT" ("FILENAME" "notes.txt"))) '
                        . '("TEXT" "PLAIN" ("CHARSET" "UTF-8") NIL NIL "7BIT" 12 1 NIL ("INLINE" NIL)) "MIXED"))' . "\r\n",
                    ];
                }
                if (str_contains($command, 'HEADER.FIELDS')) {
                    $headers = "Subject: Body selected\r\n";
                    return ["* 12 FETCH ({" . strlen($headers) . "}\r\n", $headers, ")\r\n"];
                }
                if ($command === 'UID FETCH 12 (BODY.PEEK[2]<0.1048576>)') {
                    return ["* 12 FETCH ({12}\r\n", 'Visible body', ")\r\n"];
                }
                return ["A0001 OK done\r\n"];
            }

            public function close(): void
            {
            }
        };

        $source = new SocketImapMailboxSource(new MailTextExtractor(), static fn (): ImapWireConnection => $wire);
        $messages = $source->fetch(new ImapSettings('imap.example.test', 993, 'ssl', 'reader', 'test-password', 'INBOX'));

        self::assertSame('Visible body', $messages[0]['text']);
        self::assertContains('UID FETCH 12 (BODY.PEEK[2]<0.1048576>)', $wire->commands);
        self::assertNotContains('UID FETCH 12 (BODY.PEEK[1]<0.1048576>)', $wire->commands);
    }
}
