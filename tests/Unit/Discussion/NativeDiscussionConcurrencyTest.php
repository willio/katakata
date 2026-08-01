<?php
declare(strict_types=1);

namespace Katakata\Tests\Unit\Discussion;

use DateTimeImmutable;
use Katakata\Discussion\DiscussionReference;
use Katakata\Discussion\NativeDiscussionMaintenance;
use Katakata\Discussion\NativeDiscussionStore;
use Katakata\Editorial\AtomicFile;
use PHPUnit\Framework\TestCase;

final class NativeDiscussionConcurrencyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-native-discussion-concurrency-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->root);
    }

    public function testConcurrentSubmissionsReReadAfterTheThreadLockAndRetainEveryEntry(): void
    {
        $store = new NativeDiscussionStore($this->root, new AtomicFile());
        $reference = $store->create('shared-thread', 'shared-thread');
        $lock = fopen($this->root . '/shared-thread.lock', 'c');
        self::assertIsResource($lock);
        self::assertTrue(flock($lock, LOCK_EX));

        $children = [
            $this->startSubmitter($reference, 'First writer'),
            $this->startSubmitter($reference, 'Second writer'),
        ];

        try {
            foreach ($children as $child) {
                self::assertTrue($this->waitForFile($child['ready']), (string) file_get_contents($child['error']));
                touch($child['go']);
            }
            self::assertFalse($this->waitForFile($children[0]['done'], 300), 'A writer bypassed the per-thread lock.');

            $threadPath = $this->root . '/shared-thread.json';
            $data = json_decode((string) file_get_contents($threadPath), true, flags: JSON_THROW_ON_ERROR);
            $data['entries'][] = [
                'id' => 'entry-already-written',
                'author_name' => 'Existing writer',
                'body' => 'An entry written while submitters wait.',
                'published_at' => '2026-08-01T00:00:00+00:00',
                'parent_id' => null,
                'status' => 'pending',
                'spam' => [],
            ];
            file_put_contents($threadPath, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        foreach ($children as $child) {
            self::assertTrue($this->waitForFile($child['done']));
            self::assertSame(0, proc_close($child['process']));
        }

        $thread = json_decode((string) file_get_contents($this->root . '/shared-thread.json'), true, flags: JSON_THROW_ON_ERROR);
        $identities = array_map(static fn (array $entry): string => $entry['id'] === 'entry-already-written' ? $entry['id'] : $entry['author_name'], $thread['entries']);
        self::assertSame('entry-already-written', $identities[0]);
        self::assertEqualsCanonicalizing(['First writer', 'Second writer'], array_slice($identities, 1));
    }

    public function testPruneWaitsForTheSameThreadLock(): void
    {
        $threadPath = $this->root . '/expired-thread.json';
        file_put_contents($threadPath, json_encode([
            'version' => 1,
            'id' => 'expired-thread',
            'post_slug' => 'expired-thread',
            'created_at' => '2026-07-01T00:00:00+00:00',
            'entries' => [[
                'id' => 'expired-entry',
                'author_name' => 'Reader',
                'body' => 'Rejected.',
                'published_at' => '2026-07-01T00:00:00+00:00',
                'status' => 'rejected',
                'moderated_at' => '2026-07-01T00:00:00+00:00',
            ]],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");

        $lock = fopen($this->root . '/expired-thread.lock', 'c');
        self::assertIsResource($lock);
        self::assertTrue(flock($lock, LOCK_EX));
        $child = $this->startPruner();

        try {
            self::assertTrue($this->waitForFile($child['ready']), (string) file_get_contents($child['error']));
            touch($child['go']);
            self::assertFalse($this->waitForFile($child['done'], 300), 'Pruning bypassed the per-thread lock.');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        self::assertTrue($this->waitForFile($child['done']));
        self::assertSame(0, proc_close($child['process']));
        self::assertFileDoesNotExist($threadPath);
    }

    public function testModerationWaitsForTheSameThreadLock(): void
    {
        $store = new NativeDiscussionStore($this->root, new AtomicFile());
        $reference = $store->create('moderated-thread', 'moderated-thread');
        $entry = $store->submit($reference, 'Reader', 'Please approve this.');
        $lock = fopen($this->root . '/moderated-thread.lock', 'c');
        self::assertIsResource($lock);
        self::assertTrue(flock($lock, LOCK_EX));
        $child = $this->startModerator($entry->id);

        try {
            self::assertTrue($this->waitForFile($child['ready']), (string) file_get_contents($child['error']));
            touch($child['go']);
            self::assertFalse($this->waitForFile($child['done'], 300), 'Moderation bypassed the per-thread lock.');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        self::assertTrue($this->waitForFile($child['done']));
        self::assertSame(0, proc_close($child['process']));
        self::assertSame($entry->id, $store->fetch($reference)->entries[0]->id);
    }

    /** @return array{process: resource, ready: string, go: string, done: string, error: string} */
    private function startSubmitter(DiscussionReference $reference, string $author): array
    {
        $script = <<<'PHP'
require $argv[1];
$store = new Katakata\Discussion\NativeDiscussionStore($argv[2], new Katakata\Editorial\AtomicFile());
$reference = new Katakata\Discussion\DiscussionReference('native', 'shared-thread');
file_put_contents($argv[3], 'ready');
while (!is_file($argv[4])) { usleep(1000); }
$store->submit($reference, $argv[5], 'A concurrent comment.');
file_put_contents($argv[6], 'done');
PHP;

        return $this->startChild($script, [$author]);
    }

    /** @return array{process: resource, ready: string, go: string, done: string, error: string} */
    private function startPruner(): array
    {
        $script = <<<'PHP'
require $argv[1];
$maintenance = new Katakata\Discussion\NativeDiscussionMaintenance($argv[2]);
file_put_contents($argv[3], 'ready');
while (!is_file($argv[4])) { usleep(1000); }
$maintenance->prune(1, new DateTimeImmutable('2026-08-01T00:00:00+00:00'));
file_put_contents($argv[5], 'done');
PHP;

        return $this->startChild($script, []);
    }

    /** @return array{process: resource, ready: string, go: string, done: string, error: string} */
    private function startModerator(string $entryId): array
    {
        $script = <<<'PHP'
require $argv[1];
$store = new Katakata\Discussion\NativeDiscussionStore($argv[2], new Katakata\Editorial\AtomicFile());
$reference = new Katakata\Discussion\DiscussionReference('native', 'moderated-thread');
file_put_contents($argv[3], 'ready');
while (!is_file($argv[4])) { usleep(1000); }
$store->moderate($reference, $argv[5], 'approved');
file_put_contents($argv[6], 'done');
PHP;

        return $this->startChild($script, [$entryId]);
    }

    /** @param list<string> $extra @return array{process: resource, ready: string, go: string, done: string, error: string} */
    private function startChild(string $script, array $extra): array
    {
        $suffix = bin2hex(random_bytes(4));
        $ready = $this->root . '/child-' . $suffix . '.ready';
        $go = $this->root . '/child-' . $suffix . '.go';
        $done = $this->root . '/child-' . $suffix . '.done';
        $error = $this->root . '/child-' . $suffix . '.err';
        $command = [PHP_BINARY, '-r', $script, dirname(__DIR__, 3) . '/bootstrap/autoload.php', $this->root, $ready, $go];
        $command = [...$command, ...$extra, $done];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['file', $error, 'w']], $pipes);
        self::assertIsResource($process);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        return compact('process', 'ready', 'go', 'done', 'error');
    }

    private function waitForFile(string $path, int $milliseconds = 1000): bool
    {
        $deadline = hrtime(true) + ($milliseconds * 1_000_000);
        while (hrtime(true) < $deadline) {
            if (is_file($path)) {
                return true;
            }
            usleep(1_000);
        }
        return is_file($path);
    }
}
