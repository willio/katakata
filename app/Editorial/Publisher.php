<?php

declare(strict_types=1);

namespace Katakata\Editorial;

use DateTimeImmutable;
use Katakata\Content\Draft;
use RuntimeException;

final class Publisher
{
    public function __construct(
        private readonly string $postsPath,
        private readonly AtomicFile $files,
        private readonly RevisionStore $revisions,
    ) {
    }

    public function publish(Draft $draft, ?DateTimeImmutable $at = null): string
    {
        $at ??= new DateTimeImmutable();
        $year = (int) $at->format('Y');
        if ($year < 2000 || $year > 2099) {
            throw new RuntimeException(
                "Publish date [{$at->format('Y-m-d')}] is outside the supported 2000-2099 range."
            );
        }

        $target = sprintf(
            '%s/%s/%s/%s_%s.md',
            $this->postsPath,
            $at->format('Y'),
            $at->format('m'),
            $at->format('ymd'),
            $draft->slug,
        );

        if (is_file($target)) {
            throw new RuntimeException("Published article already exists at [{$target}].");
        }

        $meta = $draft->meta;
        unset($meta['updated_at'], $meta['publish_at']);
        $meta['title'] = $draft->title;
        $meta['date'] = $at->format('Y-m-d');
        $meta['status'] = 'published';

        $this->revisions->capture($draft->slug, $draft->path, $at);
        $this->files->write($target, Document::markdown($meta, $this->stripDerivedTitle($draft->body, $draft->title)));

        if (!unlink($draft->path)) {
            unlink($target);
            throw new RuntimeException("Unable to remove published draft [{$draft->path}].");
        }

        return $target;
    }

    /**
     * The editor derives the title from the body's first line, which is kept
     * while drafting so the derivation keeps working. On publish the line is
     * removed — but only when it still matches the derived title exactly.
     */
    private function stripDerivedTitle(string $body, string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return $body;
        }

        [$first, $rest] = array_pad(explode("\n", $body, 2), 2, '');
        $derived = trim((string) preg_replace('/^\s{0,3}#{1,6}\s+/', '', $first));
        if ($derived !== $title) {
            return $body;
        }

        return ltrim($rest, "\r\n");
    }
}
