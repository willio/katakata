<?php

declare(strict_types=1);

use Katakata\Email\MailboxSyncCoordinator;

/** @var \Katakata\Application $app */
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
require dirname(__DIR__, 2) . '/bootstrap/mail.php';

$accountId = null;
$limit = 100;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--account=')) {
        $accountId = substr($argument, strlen('--account='));
        continue;
    }
    if (str_starts_with($argument, '--limit=')) {
        $limit = max(1, (int) substr($argument, strlen('--limit=')));
        continue;
    }
    if (ctype_digit($argument)) {
        $limit = max(1, (int) $argument);
    }
}

$coordinator = $app->make(MailboxSyncCoordinator::class);

try {
    $results = $accountId === null
        ? $coordinator->syncEnabled($limit)
        : [$accountId => ['status' => 'ready'] + $coordinator->syncAccount($accountId, $limit)];

    $failed = false;
    foreach ($results as $id => $result) {
        $status = (string) ($result['status'] ?? 'error');
        $label = (string) ($result['label'] ?? $id);
        if ($status === 'ready') {
            fwrite(STDOUT, sprintf(
                "%s (%s): %d fetched, %d updated. Last sync: %s\n",
                $label,
                $id,
                (int) ($result['fetched'] ?? 0),
                (int) ($result['written'] ?? 0),
                (string) ($result['last_synced_at'] ?? 'unknown'),
            ));
            continue;
        }
        $failed = true;
        fwrite(STDERR, sprintf(
            "%s (%s): sync failed: %s\n",
            $label,
            $id,
            (string) ($result['error'] ?? 'Mailbox synchronization failed.'),
        ));
    }
    exit($failed ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, "Mailbox sync failed: {$error->getMessage()}\n");
    exit(1);
}
