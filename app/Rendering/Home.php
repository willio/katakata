<?php

declare(strict_types=1);

namespace Katakata\Rendering;

use Katakata\Content\Collection;
use Katakata\Content\Post;

final class Home
{
    /**
     * @param Collection<Post> $posts
     * @return array{
     *     lead: Post|null,
     *     recent: array<int, Post>,
     *     earlierThisYear: array<string, array<int, Post>>,
     *     archiveYear: string|null
     * }
     */
    public function layout(Collection $posts): array
    {
        $ordered = $this->latest($posts, $posts->count());
        $lead = $ordered[0] ?? null;
        $earlierThisYear = [];
        $archiveYear = null;

        if ($lead === null) {
            return [
                'lead' => null,
                'recent' => [],
                'earlierThisYear' => [],
                'archiveYear' => null,
            ];
        }

        $leadYear = $lead->date->format('Y');

        foreach ($ordered as $post) {
            if ($post->date->format('Y') !== $leadYear) {
                $archiveYear = $post->date->format('Y');
                break;
            }
        }

        foreach (array_slice($ordered, 7) as $post) {
            if ($post->date->format('Y') === $leadYear) {
                $earlierThisYear[$post->date->format('Y-m')][] = $post;
            }
        }

        return [
            'lead' => $lead,
            'recent' => array_slice($ordered, 1, 6),
            'earlierThisYear' => $earlierThisYear,
            'archiveYear' => $archiveYear,
        ];
    }

    /**
     * @param Collection<Post> $posts
     * @return array<int, Post>
     */
    public function latest(Collection $posts, int $limit = 6): array
    {
        if ($limit < 1) {
            return [];
        }

        $published = array_filter(
            $posts->all(),
            static fn (Post $post): bool => $post->isPublished(),
        );

        usort(
            $published,
            static fn (Post $left, Post $right): int => $right->date <=> $left->date,
        );

        return array_slice($published, 0, $limit);
    }
}
