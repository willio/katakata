<?php

declare(strict_types=1);

namespace Katakata\Discussion;

interface DiscussionProvider
{
    public function key(): string;

    public function isAvailable(): bool;

    public function supportsReplies(): bool;

    /** @param array<string, mixed> $post */
    public function create(array $post): DiscussionReference;

    public function fetch(DiscussionReference $reference): DiscussionThread;

    /** @return list<DiscussionEntry> */
    public function recent(int $limit = 8): array;

    /** @return array{threads: int, entries: int, failed: int} */
    public function synchronize(): array;
}
