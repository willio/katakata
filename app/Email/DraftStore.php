<?php

declare(strict_types=1);

namespace Katakata\Email;

interface DraftStore
{
    public function create(Draft $draft): Draft;

    public function save(Draft $draft, int $expectedVersion): Draft;

    public function find(string $id): ?Draft;

    /** @return list<Draft> */
    public function recent(int $limit = 8): array;

    public function delete(string $id): void;

    public function deleteIfVersion(string $id, int $expectedVersion): bool;
}
