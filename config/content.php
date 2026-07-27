<?php

declare(strict_types=1);

/**
 * Canonical content paths, per the Master Specification's
 * "Content Storage" section. These are relative to the application
 * base path; the Content Engine (Phase 1) resolves them absolutely.
 */
return [
    'posts_path' => 'content/posts',
    'drafts_path' => 'content/drafts',
    'authors_path' => 'content/authors',
    'assets_path' => 'content/assets',
];
