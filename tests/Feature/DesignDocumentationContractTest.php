<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use PHPUnit\Framework\TestCase;

final class DesignDocumentationContractTest extends TestCase
{
    public function testPublicAndOwnerTypographyRolesAreCanonical(): void
    {
        $design = file_get_contents(dirname(__DIR__, 2) . '/docs/design_specification.md');
        $components = file_get_contents(dirname(__DIR__, 2) . '/docs/fields-buttons-styleguide.md');

        self::assertIsString($design);
        self::assertIsString($components);
        self::assertStringContainsString('Source Serif 4 remains Katakata’s editorial serif', $design);
        self::assertStringContainsString('Public display expression comes from scale, measure, weight, rhythm, and restrained italics', $design);
        self::assertStringContainsString('Normal owner controls use a 6px radius', $components);
        self::assertStringContainsString('Full pills are reserved for compact filters and state badges', $components);
    }
}
