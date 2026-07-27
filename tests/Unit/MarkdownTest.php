<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Rendering\Markdown;
use PHPUnit\Framework\TestCase;

final class MarkdownTest extends TestCase
{
    public function testItRendersTheSupportedProseSubset(): void
    {
        $html = (new Markdown())->render("# A title\n\nA **strong** paragraph with [a link](https://example.com).\n\n- one\n- two");

        self::assertStringContainsString('<h1>A title</h1>', $html);
        self::assertStringContainsString('<strong>strong</strong>', $html);
        self::assertStringContainsString('<a href="https://example.com">a link</a>', $html);
        self::assertStringContainsString("<ul>\n<li>one</li>\n<li>two</li>\n</ul>", $html);
    }

    public function testItEscapesHtmlAndRejectsUnsafeLinkSchemes(): void
    {
        $html = (new Markdown())->render('<script>alert(1)</script> [click](javascript:alert(1))');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('click', $html);
    }

    public function testItEscapesFencedCode(): void
    {
        $html = (new Markdown())->render("\x60\x60\x60html\n<b>unsafe</b>\n\x60\x60\x60");

        self::assertSame('<pre><code>&lt;b&gt;unsafe&lt;/b&gt;</code></pre>', $html);
    }
}
