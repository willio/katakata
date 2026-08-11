<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Post;
use Katakata\Content\Repository;
use Katakata\Rendering\AuthorResolver;
use PHPUnit\Framework\TestCase;

final class AuthorResolverTest extends TestCase
{
    public function testItResolvesOnlyAnExactAuthorSlug(): void
    {
        $resolver = new AuthorResolver();

        self::assertSame('Test Author', $resolver->forPost($this->post('test-author'), $this->repository())?->name);
        self::assertNull($resolver->forPost($this->post('Test Author'), $this->repository()));
    }

    private function repository(): Repository
    {
        $base = dirname(__DIR__) . '/Fixtures/content';

        return new Repository($base . '/posts', $base . '/drafts', $base . '/authors', $base . '/assets');
    }

    private function post(string $author): Post
    {
        return new Post('example', 'Example', new DateTimeImmutable('2026-08-11'), $author, [], null, 'published', '', [], '');
    }
}
