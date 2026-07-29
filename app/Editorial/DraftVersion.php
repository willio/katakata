<?php

declare(strict_types=1);

namespace Katakata\Editorial;

use Katakata\Content\Draft;

final class DraftVersion
{
    public static function of(Draft $draft): string
    {
        return self::content($draft->title, $draft->body);
    }

    public static function content(string $title, string $body): string
    {
        return hash('sha256', trim($title) . "\0" . $body);
    }
}
