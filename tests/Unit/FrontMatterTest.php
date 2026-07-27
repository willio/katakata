<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Content\FrontMatter;
use PHPUnit\Framework\TestCase;

final class FrontMatterTest extends TestCase
{
    public function test_it_parses_scalars_and_inline_lists(): void
    {
        $raw = "---\ntitle: Hello World\ndraft: false\nviews: 12\ntags: [foo, bar]\n---\nBody text.";

        $result = FrontMatter::parse($raw);

        $this->assertSame('Hello World', $result['meta']['title']);
        $this->assertFalse($result['meta']['draft']);
        $this->assertSame(12, $result['meta']['views']);
        $this->assertSame(['foo', 'bar'], $result['meta']['tags']);
        $this->assertSame('Body text.', $result['body']);
    }

    public function test_it_parses_block_lists(): void
    {
        $raw = "---\ntitle: With Block List\ntags:\n  - one\n  - two\n---\nBody.";

        $result = FrontMatter::parse($raw);

        $this->assertSame(['one', 'two'], $result['meta']['tags']);
    }

    public function test_it_treats_content_without_front_matter_as_body_only(): void
    {
        $result = FrontMatter::parse("Just a plain file.\nNo front matter here.");

        $this->assertSame([], $result['meta']);
        $this->assertSame("Just a plain file.\nNo front matter here.", $result['body']);
    }

    public function test_quoted_values_preserve_their_contents(): void
    {
        $raw = "---\ntitle: \"Quoted: Title\"\n---\nBody.";

        $result = FrontMatter::parse($raw);

        $this->assertSame('Quoted: Title', $result['meta']['title']);
    }

    public function test_it_treats_unclosed_front_matter_as_body_only(): void
    {
        $raw = "---\ntitle: Missing Closing Delimiter\nNo closing marker below.";

        $result = FrontMatter::parse($raw);

        $this->assertSame([], $result['meta']);
        $this->assertSame($raw, $result['body']);
    }

    public function test_null_and_empty_inline_lists(): void
    {
        $raw = "---\ntitle: Edge Cases\nparent: null\ntags: []\n---\nBody.";

        $result = FrontMatter::parse($raw);

        $this->assertNull($result['meta']['parent']);
        $this->assertSame([], $result['meta']['tags']);
    }
}
