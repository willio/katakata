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

    public function testPublicBodyCopyHasAnUnqualified19pxMinimum(): void
    {
        $design = file_get_contents(dirname(__DIR__, 2) . '/docs/design_specification.md');

        self::assertIsString($design);
        self::assertStringContainsString(
            'Public display expression comes from scale, measure, weight, rhythm, and restrained italics, not a replacement typeface. Public body copy is at least 19px.',
            $design
        );
        self::assertStringNotContainsString('Body copy is Public', $design);
    }

    public function testHomepageFocusUsesTheInteractionToken(): void
    {
        $homepage = file_get_contents(dirname(__DIR__, 2) . '/docs/design/home-editorial-hierarchy.md');

        self::assertIsString($homepage);
        self::assertStringContainsString('outline: 2px solid var(--accent);', $homepage);
    }

    public function testRefinementReviewSeparatesViewportAssertionsFromDeferredVisualReview(): void
    {
        $review = file_get_contents(dirname(__DIR__, 2) . '/docs/design-reviews/2026-08-09-design-contract-refinement.md');

        self::assertIsString($review);
        $normalizedReview = preg_replace('/\s+/', ' ', $review) ?? $review;
        self::assertStringContainsString('Dark-mode visual assessment is deferred', $normalizedReview);
        self::assertStringContainsString('Long Indonesian-title wrapping and empty-state presentation are deferred', $normalizedReview);
        self::assertStringContainsString('not retained as durable review evidence or manually inspected for Task 6', $normalizedReview);
    }

    public function testRefinementReviewAttributesTheMatrixToTaskFiveAndNamesItsActualAssertions(): void
    {
        $review = file_get_contents(dirname(__DIR__, 2) . '/docs/design-reviews/2026-08-09-design-contract-refinement.md');

        self::assertIsString($review);
        $normalizedReview = preg_replace('/\s+/', ' ', $review) ?? $review;
        self::assertStringContainsString('Task 6 did not rerun this matrix; `42/42` is inherited Task 5 controller evidence.', $normalizedReview);
        self::assertStringContainsString('document-wide horizontal overflow', $normalizedReview);
        self::assertStringContainsString('maximum 75ch measure on the selected public secondary surfaces', $normalizedReview);
        self::assertStringContainsString('Computed font-role comparisons', $normalizedReview);
    }
}
