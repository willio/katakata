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
        $this->files->write($target, Document::markdown($meta, $draft->body));

        if (!unlink($draft->path)) {
            unlink($target);
            throw new RuntimeException("Unable to remove published draft [{$draft->path}].");
        }

        return $target;
    }
}
