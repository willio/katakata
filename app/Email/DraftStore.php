<?php

declare(strict_types=1);

namespace Katakata\Email;

interface DraftStore
{
    public function save(Draft $draft): void;

    public function find(string $id): ?Draft;

    public function delete(string $id): void;
}
