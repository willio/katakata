<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use DateTimeImmutable;
use Katakata\Content\Draft;
use Katakata\Editorial\DraftVersion;
use PHPUnit\Framework\TestCase;

final class DraftVersionTest extends TestCase
{
    public function testVersionChangesWithCanonicalDraftContent(): void
    {
        $draft = new Draft('essay', 'An essay', new DateTimeImmutable(), 'First.', [], '/tmp/essay.md');

        self::assertSame(DraftVersion::content('An essay', 'First.'), DraftVersion::of($draft));
        self::assertNotSame(DraftVersion::of($draft), DraftVersion::content('An essay', 'Second.'));
    }

    public function testTitleWhitespaceMatchesSavedTitleNormalization(): void
    {
        self::assertSame(
            DraftVersion::content('An essay', 'Body.'),
            DraftVersion::content('  An essay  ', 'Body.'),
        );
    }
}
