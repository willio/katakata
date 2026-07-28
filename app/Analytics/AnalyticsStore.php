<?php

declare(strict_types=1);

namespace Katakata\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class AnalyticsStore
{
    private ?PDO $connection = null;

    public function __construct(private readonly string $path)
    {
    }

    public function record(
        string $path,
        ?string $referrer,
        ?string $region,
        string $visitorHash,
        ?DateTimeImmutable $at = null,
    ): void {
        $statement = $this->pdo()->prepare(
            'INSERT INTO visits (path, referrer, region, visitor_hash, created_at) VALUES (?, ?, ?, ?, ?)',
        );
        $statement->execute([
            $path,
            $referrer,
            $region,
            $visitorHash,
            $this->timestamp($at ?? new DateTimeImmutable('now')),
        ]);
    }

    public function summary(?DateTimeImmutable $now = null, int $recentLimit = 15): AnalyticsSummary
    {
        $now = ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));
        $current7d = $this->countSince($now->modify('-7 days'));
        $previous7d = $this->countBetween($now->modify('-14 days'), $now->modify('-7 days'));

        return new AnalyticsSummary(
            visits7d: $current7d,
            visits30d: $this->countSince($now->modify('-30 days')),
            visits365d: $this->countSince($now->modify('-365 days')),
            visitsAllTime: $this->countAll(),
            visits7dTrendPct: $this->trend($current7d, $previous7d),
            regions: $this->regions($now->modify('-30 days')),
            recentVisits: $this->recent($recentLimit),
        );
    }

    public function prune(int $retentionDays = 400, ?DateTimeImmutable $now = null): int
    {
        if ($retentionDays < 1) {
            throw new RuntimeException('Analytics retention must be at least one day.');
        }

        $cutoff = ($now ?? new DateTimeImmutable('now'))
            ->setTimezone(new DateTimeZone('UTC'))
            ->modify("-{$retentionDays} days");
        $statement = $this->pdo()->prepare('DELETE FROM visits WHERE created_at < ?');
        $statement->execute([$this->timestamp($cutoff)]);

        return $statement->rowCount();
    }

    public function available(): bool
    {
        return extension_loaded('pdo_sqlite') && class_exists(PDO::class);
    }

    private function pdo(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }
        if (!$this->available()) {
            throw new RuntimeException('Analytics requires the pdo_sqlite PHP extension.');
        }

        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create analytics directory [{$directory}].");
        }

        $this->connection = new PDO('sqlite:' . $this->path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->connection->exec('PRAGMA journal_mode = WAL');
        $this->connection->exec('PRAGMA busy_timeout = 3000');
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS visits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                path TEXT NOT NULL,
                referrer TEXT,
                region TEXT,
                visitor_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_visits_created_at ON visits (created_at)');
        $this->connection->exec('CREATE INDEX IF NOT EXISTS idx_visits_visitor_hash ON visits (visitor_hash)');

        return $this->connection;
    }

    private function countSince(DateTimeImmutable $from): int
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(DISTINCT visitor_hash) FROM visits WHERE created_at >= ?');
        $statement->execute([$this->timestamp($from)]);

        return (int) $statement->fetchColumn();
    }

    private function countBetween(DateTimeImmutable $from, DateTimeImmutable $until): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(DISTINCT visitor_hash) FROM visits WHERE created_at >= ? AND created_at < ?',
        );
        $statement->execute([$this->timestamp($from), $this->timestamp($until)]);

        return (int) $statement->fetchColumn();
    }

    private function countAll(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(DISTINCT visitor_hash) FROM visits')->fetchColumn();
    }

    /** @return array<int, array{region: string, count: int}> */
    private function regions(DateTimeImmutable $from): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT region, COUNT(*) AS count
             FROM visits
             WHERE created_at >= ? AND region IS NOT NULL AND region <> ''
             GROUP BY region
             ORDER BY count DESC, region ASC",
        );
        $statement->execute([$this->timestamp($from)]);

        return array_map(
            static fn (array $row): array => ['region' => (string) $row['region'], 'count' => (int) $row['count']],
            $statement->fetchAll(),
        );
    }

    /** @return array<int, RecentVisit> */
    private function recent(int $limit): array
    {
        $limit = max(1, min(100, $limit));
        $statement = $this->pdo()->query(
            "SELECT path, referrer, region, created_at FROM visits ORDER BY created_at DESC, id DESC LIMIT {$limit}",
        );

        return array_map(
            static fn (array $row): RecentVisit => new RecentVisit(
                new DateTimeImmutable((string) $row['created_at']),
                (string) $row['path'],
                isset($row['referrer']) ? (string) $row['referrer'] : null,
                isset($row['region']) ? (string) $row['region'] : null,
            ),
            $statement->fetchAll(),
        );
    }

    private function trend(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function timestamp(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
