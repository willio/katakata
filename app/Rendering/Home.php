<?php

declare(strict_types=1);

namespace Katakata\Rendering;

use DateTimeImmutable;
use Katakata\Content\Collection;
use Katakata\Content\Post;

final class Home
{
    /** @return array{lead:Post|null,months:array<string,array{label:string,posts:list<Post>,has_more:bool,browse_url:string,show_author:bool}>} */
    public function layout(Collection $posts, ?DateTimeImmutable $now = null): array
    {
        $ordered = $this->latest($posts, $posts->count());
        $lead = array_shift($ordered);
        if (!$lead instanceof Post) {
            return ['lead' => null, 'months' => []];
        }

        $groups = [];
        foreach ($ordered as $post) {
            $groups[$post->date->format('Y-m')][] = $post;
        }

        $now ??= new DateTimeImmutable('now');
        $months = [];
        foreach ($groups as $key => $monthPosts) {
            $position = count($months);
            $limit = match ($position) { 0 => 12, 1 => 9, 2 => 6, default => 3 };
            $pageCount = (int) ceil(count($monthPosts) / $limit);
            $page = $this->weekIndex($now) % $pageCount;
            [$year, $month] = explode('-', $key, 2);
            $months[$key] = [
                'label' => $monthPosts[0]->date->format('F Y'),
                'posts' => array_slice($monthPosts, $page * $limit, $limit),
                'has_more' => count($monthPosts) > $limit,
                'browse_url' => "/archive?year={$year}&month={$month}",
                'show_author' => $position < 3,
            ];
        }

        return ['lead' => $lead, 'months' => $months];
    }

    /** @return list<Post> */
    public function latest(Collection $posts, int $limit = 6): array
    {
        if ($limit < 1) return [];
        $published = array_filter($posts->all(), static fn (Post $post): bool => $post->isPublished());
        usort($published, static fn (Post $left, Post $right): int => $right->date <=> $left->date);
        return array_slice($published, 0, $limit);
    }

    private function weekIndex(DateTimeImmutable $now): int
    {
        return intdiv($now->getTimestamp(), 7 * 24 * 60 * 60);
    }
}
