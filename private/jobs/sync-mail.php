<?php

declare(strict_types=1);

use Katakata\Email\ImapSynchronizer;

/** @var \Katakata\Application $app */
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
require dirname(__DIR__, 2) . '/bootstrap/mail.php';
$limit = isset($argv[1]) ? max(1, (int) $argv[1]) : 100;

try {
    $result = $app->make(ImapSynchronizer::class)->sync($limit);
    fwrite(STDOUT, sprintf(
        "Mailbox sync: %d fetched, %d cache files updated. Last sync: %s\n",
        $result['fetched'],
        $result['written'],
        $result['last_synced_at'],
    ));
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, "Mailbox sync failed: {$error->getMessage()}\n");
    exit(1);
}
