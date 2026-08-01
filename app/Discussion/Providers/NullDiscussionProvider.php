<?php

declare(strict_types=1);

namespace Katakata\Discussion\Providers;

use Katakata\Discussion\DiscussionProvider;
use Katakata\Discussion\DiscussionReference;
use Katakata\Discussion\DiscussionThread;
use LogicException;

final class NullDiscussionProvider implements DiscussionProvider
{
    public function key(): string
    {
        return 'none';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function supportsReplies(): bool
    {
        return false;
    }

    public function create(array $post): DiscussionReference
    {
        throw new LogicException('Discussion is disabled.');
    }

    public function fetch(DiscussionReference $reference): DiscussionThread
    {
        return new DiscussionThread($reference);
    }

    public function recent(int $limit = 8): array
    {
        return [];
    }

    public function synchronize(): array
    {
        return ['threads' => 0, 'entries' => 0, 'failed' => 0];
    }
}
