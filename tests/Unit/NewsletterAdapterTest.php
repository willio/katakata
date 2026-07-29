<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Post;
use Katakata\Distribution\NewsletterAdapter;
use Katakata\Editorial\AtomicFile;
use Katakata\Rendering\Markdown;
use PHPUnit\Framework\TestCase;

final class NewsletterAdapterTest extends TestCase
{
    public function testItCreatesAProviderNeutralPayloadFromThePost(): void
    {
        $root = sys_get_temp_dir() . '/katakata-newsletter-' . bin2hex(random_bytes(6));
        $adapter = new NewsletterAdapter($root, 'https://example.com/', new Markdown(), new AtomicFile());
        $post = new Post(
            'essay',
            'An essay',
            new DateTimeImmutable('2026-07-28'),
            null,
            [],
            null,
            'published',
            'Hello **reader**.',
            [],
            '/tmp/essay.md',
        );

        $result = $adapter->distribute($post);
        $payload = json_decode((string) file_get_contents($result['path']), true);

        self::assertSame('https://example.com/2026/07/essay', $payload['canonical_url']);
        self::assertStringContainsString('<strong>reader</strong>', $payload['html']);
        self::assertStringContainsString('Hello reader.', $payload['text']);

        unlink($result['path']);
        rmdir(dirname($result['path']));
        rmdir(dirname(dirname($result['path'])));
        rmdir($root);
    }
}
