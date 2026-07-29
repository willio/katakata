<?php

declare(strict_types=1);

namespace Katakata\Distribution;

interface ThreadsApi
{
    /** @return array{id: string, permalink: ?string} */
    public function publish(string $text): array;

    /** @return list<array{id: string, text: string, username: string, timestamp: string, permalink: string, avatar_url: ?string}> */
    public function replies(string $mediaId): array;
}
