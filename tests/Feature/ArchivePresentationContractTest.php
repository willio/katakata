<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class ArchivePresentationContractTest extends TestCase
{
    public function testArchiveUsesCompactEditorialRows(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/archive.php');

        self::assertIsString($view);
        self::assertStringContainsString('class="archive-entry"', $view);
        self::assertStringContainsString('class="archive-entry-date"', $view);
        self::assertStringContainsString('class="archive-entry-copy"', $view);
    }

    public function testArchiveStylesUseResponsiveTwoColumnEditorialRows(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/site.css');

        self::assertIsString($css);
        self::assertStringContainsString('.archive-entry {', $css);
        self::assertStringContainsString('grid-template-columns: 7rem minmax(0, 1fr);', $css);
        self::assertStringContainsString('.archive-entry-date {', $css);
        self::assertStringContainsString('.archive-entry-copy h2 {', $css);
        self::assertStringContainsString('font-size: clamp(1.2rem, 2.8vw, 1.45rem);', $css);
        self::assertStringContainsString('@media (max-width: 34rem)', $css);
    }
}
