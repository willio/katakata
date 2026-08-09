<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class PublicEditorialHierarchyContractTest extends TestCase
{
    public function testPublicSecondaryPagesUseEditorialHierarchyClasses(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['article.php', 'archive.php', 'author.php', 'newsletter.php'] as $view) {
            $source = file_get_contents($root . '/resources/views/' . $view);
            self::assertIsString($source);
            self::assertStringContainsString('publication-page', $source, $view);
        }

        $css = file_get_contents($root . '/public/assets/css/site.css');
        self::assertIsString($css);
        self::assertStringContainsString('.publication-title', $css);
        self::assertStringContainsString('.publication-index-title', $css);
    }

    public function testPublicSecondaryPageBodiesIdentifyThePublicationSurface(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['article.php', 'archive.php', 'author.php', 'newsletter.php'] as $view) {
            $source = file_get_contents($root . '/resources/views/' . $view);
            self::assertIsString($source);
            self::assertStringContainsString('<body class="publication-page">', $source, $view);
        }
    }
}
