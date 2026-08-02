<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use Katakata\Email\MailTextExtractor;
use PHPUnit\Framework\TestCase;

final class MailTextExtractorTest extends TestCase
{
    public function testItExtractsPlainTextAndDecodesHeaders(): void
    {
        $raw = "From: Reader <reader@example.test>\r\n"
            . "To: Letters <letters@example.test>\r\n"
            . "Subject: A note\r\n"
            . "Date: Fri, 1 Aug 2026 10:00:00 +0000\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . "Visible text\r\n";

        $message = (new MailTextExtractor())->extract($raw);

        self::assertSame('Reader <reader@example.test>', $message['from']);
        self::assertSame('Letters <letters@example.test>', $message['to']);
        self::assertSame('A note', $message['subject']);
        self::assertSame('Visible text', $message['text']);
    }

    public function testItIgnoresAttachmentParts(): void
    {
        $raw = "From: reader@example.test\r\nTo: letters@example.test\r\nSubject: Multipart\r\n"
            . "Content-Type: multipart/mixed; boundary=boundary42\r\n\r\n"
            . "--boundary42\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nVisible text\r\n"
            . "--boundary42\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=file.pdf\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\nU0VDUkVU\r\n--boundary42--\r\n";

        $message = (new MailTextExtractor())->extract($raw);

        self::assertSame('Visible text', $message['text']);
        self::assertStringNotContainsString('SECRET', $message['text']);
    }

    public function testItRecursesIntoMultipartAlternativeInsideMixedEnvelope(): void
    {
        $raw = "From: reader@example.test\r\nSubject: Nested\r\n"
            . "Content-Type: multipart/mixed; boundary=outer\r\n\r\n"
            . "--outer\r\nContent-Type: multipart/alternative; boundary=inner\r\n\r\n"
            . "--inner\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nVisible=20text\r\n"
            . "--inner\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<p>Visible text</p>\r\n--inner--\r\n"
            . "--outer\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=file.pdf\r\n\r\nSECRET\r\n--outer--\r\n";

        $message = (new MailTextExtractor())->extract($raw);

        self::assertSame('Visible text', $message['text']);
        self::assertStringNotContainsString('SECRET', $message['text']);
    }
}
