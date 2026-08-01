<?php
declare(strict_types=1);

namespace Katakata\Tests\Unit\Discussion;

use DateTimeImmutable;
use Katakata\Discussion\DiscussionReference;
use Katakata\Discussion\NativeDiscussionStore;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NativeDiscussionSubmissionGuardTest extends TestCase
{
    private string $root;
    private NativeDiscussionStore $store;
    private DiscussionReference $reference;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-native-discussion-guard-' . bin2hex(random_bytes(6));
        $this->store = new NativeDiscussionStore($this->root, new AtomicFile());
        $this->reference = $this->store->create('guarded-thread', 'guarded-thread');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->root);
    }

    public function testItRejectsAuthorNamesLongerThan120UnicodeCharacters(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Author name must not exceed 120 characters.');

        $this->store->submit($this->reference, str_repeat('界', 121), 'A considered response.');
    }

    public function testItRejectsBodiesLongerThan5000UnicodeCharacters(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Comment body must not exceed 5000 characters.');

        $this->store->submit($this->reference, 'Reader', str_repeat('界', 5001));
    }

    public function testItRejectsFilledHoneypots(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Spam check failed.');

        $this->store->submit($this->reference, 'Reader', 'A considered response.', spam: ['honeypot' => 'https://spam.example']);
    }

    public function testItEnforcesCooldownForTheSameAuthor(): void
    {
        $first = new DateTimeImmutable('2026-08-01T00:00:00+00:00');
        $this->store->submit($this->reference, 'Reader', 'First response.', now: $first);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Please wait before submitting another comment.');
        $this->store->submit($this->reference, 'Reader', 'Second response.', now: $first->modify('+4 seconds'));
    }
}
