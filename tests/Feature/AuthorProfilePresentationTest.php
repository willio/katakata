<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use DateTimeImmutable;
use Katakata\Content\Author;
use Katakata\Content\Post;
use Katakata\View;
use PHPUnit\Framework\TestCase;

final class AuthorProfilePresentationTest extends TestCase
{
    public function testAuthorPageRendersAQuietProfileAndSafeSocialLinks(): void
    {
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('author', [
            'author' => new Author('examplewriter', 'Example Writer', 'A calm writer.', null, [], '', ['https://example.com/examplewriter']),
            'siteName' => 'Katakata',
            'bioHtml' => '<p>A calm writer.</p>',
            'posts' => [$this->post()],
        ]);

        self::assertStringContainsString('author-header', $html);
        self::assertStringContainsString('class="author-social"', $html);
        self::assertStringContainsString('href="https://example.com/examplewriter"', $html);
        self::assertStringContainsString('>example.com</a>', $html);
        self::assertStringContainsString('rel="noopener noreferrer"', $html);
        self::assertStringContainsString('01 JUN 2018', $html);
    }

    public function testPublicBylinesOnlyLinkResolvedProfiles(): void
    {
        $home = file_get_contents(dirname(__DIR__, 2) . '/resources/views/home.php');
        $article = file_get_contents(dirname(__DIR__, 2) . '/resources/views/article.php');

        self::assertIsString($home);
        self::assertIsString($article);
        self::assertStringContainsString('/authors/<?= e($leadAuthor->slug) ?>', $home);
        self::assertStringContainsString('/authors/<?= e($author->slug) ?>', $article);
    }

    public function testPriorityHomeShelfLinksAResolvedAuthorByline(): void
    {
        $post = $this->post();
        $html = (new View(dirname(__DIR__, 2) . '/resources/views'))->render('home', [
            'name' => 'Katakata', 'tagline' => '', 'siteUrl' => 'https://katakata.test',
            'lead' => $post, 'leadAuthor' => null,
            'authors' => ['examplewriter' => new Author('examplewriter', 'Example Writer', null, null, [], '')],
            'months' => ['2018-06' => ['label' => 'June 2018', 'posts' => [$post], 'has_more' => false, 'browse_url' => '/archive?year=2018&month=06', 'show_author' => true]],
        ]);

        self::assertStringContainsString('/authors/examplewriter">EXAMPLE WRITER</a>', $html);
    }

    private function post(): Post
    {
        return new Post('a-quiet-room', 'A Quiet Room', new DateTimeImmutable('2018-06-01'), 'examplewriter', [], null, 'published', '', [], '');
    }
}
