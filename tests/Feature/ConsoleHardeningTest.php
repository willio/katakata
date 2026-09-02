<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ConsoleHardeningTest extends TestCase
{
    private string $root;
    private string $runner;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-console-hardening-' . bin2hex(random_bytes(6));
        foreach (['content/posts', 'content/drafts', 'content/authors', 'content/assets', 'storage/auth'] as $path) {
            mkdir($this->root . '/' . $path, 0775, true);
        }

        $this->runner = $this->root . '/console.php';
        file_put_contents($this->runner, str_replace(
            '{{AUTOLOAD}}',
            var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true),
            <<<'PHP'
            <?php

            declare(strict_types=1);

            require {{AUTOLOAD}};

            use Katakata\Application as Kernel;
            use Katakata\Auth\AccountStore;
            use Katakata\Console\Application as ConsoleApplication;
            use Katakata\Content\Repository;
            use Katakata\Editorial\AtomicFile;
            use Katakata\Editorial\DraftEditor;
            use Katakata\Editorial\Editor;
            use Katakata\Editorial\Publisher;
            use Katakata\Editorial\RevisionStore;
            use Katakata\Editorial\Scheduler;

            $root = $argv[1];
            $app = new Kernel($root);
            $app->config()->set('app', ['url' => 'https://example.test']);
            $app->config()->set('content', [
                'posts_path' => 'content/posts',
                'drafts_path' => 'content/drafts',
                'authors_path' => 'content/authors',
                'assets_path' => 'content/assets',
            ]);
            $app->config()->freeze();

            $files = new AtomicFile();
            $revisions = new RevisionStore($root . '/content/revisions', $files);
            $app->instance(Repository::class, Repository::forApplication($app));
            $app->instance(Publisher::class, new Publisher($root . '/content/posts', $files, $revisions));
            $app->instance(Scheduler::class, new Scheduler());
            $app->instance(DraftEditor::class, new DraftEditor($root . '/content/drafts', $files, $revisions));
            $app->instance(Editor::class, new Editor($files, $revisions));
            $app->instance(AccountStore::class, new AccountStore($root . '/storage/auth/accounts.json', $files));

            exit((new ConsoleApplication($app))->run(array_slice($argv, 2)));
            PHP,
        ));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function testBareInvocationListsCommandsWithDescriptions(): void
    {
        [$code, $stdout] = $this->command([]);

        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: php bin/katakata <command>', $stdout);
        self::assertStringContainsString('Available commands:', $stdout);
        self::assertStringContainsString('draft:create', $stdout);
        self::assertStringContainsString('Create a new draft.', $stdout);
    }

    public function testGlobalHelpFlagListsCommands(): void
    {
        [$code, $stdout] = $this->command(['--help']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Available commands:', $stdout);
        self::assertStringContainsString('publish:due', $stdout);
    }

    public function testHelpCommandShowsPerCommandUsage(): void
    {
        [$code, $stdout] = $this->command(['help', 'serve']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: php bin/katakata serve [host:port]', $stdout);
    }

    public function testPerCommandHelpDoesNotAct(): void
    {
        $this->draft('due-draft', 'Due', "status: scheduled\npublish_at: 2020-01-01T00:00:00+00:00");

        [$code, $stdout] = $this->command(['publish:due', '--help']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: php bin/katakata publish:due', $stdout);
        self::assertStringNotContainsString('published', $stdout);
        self::assertFileExists($this->root . '/content/drafts/due-draft.md');
        self::assertSame([], glob($this->root . '/content/posts/*/*/*.md') ?: []);
    }

    public function testPruneHelpDoesNotPrune(): void
    {
        [$code, $stdout] = $this->command(['analytics:prune', '--help']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: php bin/katakata analytics:prune', $stdout);
        self::assertStringNotContainsString('Pruned', $stdout);
    }

    public function testUnknownCommandStillFails(): void
    {
        [$code, $stdout, $stderr] = $this->command(['nope']);

        self::assertSame(1, $code);
        self::assertStringContainsString('Unknown command [nope].', $stderr);
        self::assertStringContainsString('Available commands:', $stdout);
    }

    public function testServeRejectsInvalidAddresses(): void
    {
        foreach (['not-an-address', 'localhost', '127.0.0.1:99999', '127.0.0.1:0'] as $address) {
            [$code, $stdout, $stderr] = $this->command(['serve', $address]);

            self::assertSame(1, $code, $address);
            self::assertStringContainsString("Invalid address [{$address}].", $stderr);
            self::assertStringNotContainsString('Serving', $stdout);
        }
    }

    public function testServeFailsWhenThePortIsOccupied(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($socket);
        $address = (string) stream_socket_get_name($socket, false);

        try {
            [$code, $stdout, $stderr] = $this->command(['serve', $address]);
        } finally {
            fclose($socket);
        }

        self::assertSame(1, $code);
        self::assertStringContainsString("Cannot serve on [{$address}]:", $stderr);
        self::assertStringNotContainsString('Serving', $stdout);
    }

    public function testDraftCreateWithAnInvalidSlugIsACleanError(): void
    {
        [$code, $stdout, $stderr] = $this->command(['draft:create', 'Bad Slug', 'Title']);

        self::assertSame(1, $code);
        self::assertStringContainsString('Invalid draft slug [Bad Slug].', $stderr);
        self::assertStringNotContainsString('Uncaught', $stderr);
        self::assertStringNotContainsString('Stack trace', $stderr);
        self::assertSame([], glob($this->root . '/content/drafts/*.md') ?: []);
    }

    public function testDraftEditEditorFailureIsACleanError(): void
    {
        $this->draft('editable', 'Editable');

        [$code, $stdout, $stderr] = $this->command(
            ['draft:edit', 'editable'],
            env: ['EDITOR' => '/nonexistent-editor'],
        );

        self::assertSame(1, $code);
        self::assertStringContainsString('Editor exited with status [', $stderr);
        self::assertStringNotContainsString('Uncaught', $stderr);
        self::assertStringNotContainsString('Stack trace', $stderr);
    }

    public function testDraftPublishRejectsAnInvalidDateCleanly(): void
    {
        $this->draft('datable', 'Datable');

        [$code, $stdout, $stderr] = $this->command(['draft:publish', 'datable', 'not-a-date']);

        self::assertSame(1, $code);
        self::assertStringContainsString('Invalid date [not-a-date].', $stderr);
        self::assertStringNotContainsString('Failed to parse', $stderr);
        self::assertFileExists($this->root . '/content/drafts/datable.md');
    }

    public function testPublishDueReportsTheFailingDraftAndContinues(): void
    {
        $this->draft('blocked', 'Blocked', "status: scheduled\npublish_at: 2020-01-01T00:00:00+00:00");
        $this->draft('good-one', 'Good one', "status: scheduled\npublish_at: 2020-01-02T00:00:00+00:00");
        mkdir($this->root . '/content/posts/2020/01', 0775, true);
        file_put_contents($this->root . '/content/posts/2020/01/200101_blocked.md', "---\ntitle: Taken\ndate: 2020-01-01\nstatus: published\n---\n\nBody.\n");

        [$code, $stdout, $stderr] = $this->command(['publish:due']);

        self::assertSame(1, $code);
        self::assertStringContainsString('Failed to publish [blocked]:', $stderr);
        self::assertStringContainsString('Published', $stdout);
        self::assertStringContainsString('1 scheduled draft(s) published.', $stdout);
        self::assertFileExists($this->root . '/content/posts/2020/01/200102_good-one.md');
        self::assertFileDoesNotExist($this->root . '/content/drafts/good-one.md');
        self::assertFileExists($this->root . '/content/drafts/blocked.md');
    }

    public function testAuthOwnerReadsThePasswordFromStdin(): void
    {
        [$code, $stdout] = $this->command(
            ['auth:owner', 'owner@example.com', '--password-stdin'],
            stdin: "supersecretpassword\n",
        );

        self::assertSame(0, $code);
        self::assertStringContainsString('Owner created for owner@example.com.', $stdout);

        $store = (string) file_get_contents($this->root . '/storage/auth/accounts.json');
        self::assertStringContainsString('owner@example.com', $store);
        self::assertStringNotContainsString('supersecretpassword', $store);
    }

    public function testAuthOwnerPositionalPasswordStillWorks(): void
    {
        [$code, $stdout] = $this->command(['auth:owner', 'owner@example.com', 'supersecretpassword']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Owner created for owner@example.com.', $stdout);
    }

    public function testAuthOwnerWithoutAPasswordFailsWhenNotInteractive(): void
    {
        [$code, $stdout, $stderr] = $this->command(['auth:owner', 'owner@example.com']);

        self::assertSame(1, $code);
        self::assertStringContainsString('--password-stdin', $stderr);
        self::assertFileDoesNotExist($this->root . '/storage/auth/accounts.json');
    }

    public function testAuthOwnerHelpDocumentsTheSaferPasswordFlow(): void
    {
        [$code, $stdout] = $this->command(['auth:owner', '--help']);

        self::assertSame(0, $code);
        self::assertStringContainsString('--password-stdin', $stdout);
        self::assertStringContainsString('shell history', $stdout);
    }

    /** @param list<string> $arguments @return array{int,string,string} */
    private function command(array $arguments, ?string $stdin = null, array $env = []): array
    {
        $process = proc_open(
            array_merge([PHP_BINARY, $this->runner, $this->root], $arguments),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env === [] ? null : array_merge(getenv() ?: [], $env),
        );
        self::assertIsResource($process);

        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    private function draft(string $slug, string $title, string $additionalMeta = ''): void
    {
        $meta = $additionalMeta === '' ? '' : $additionalMeta . "\n";
        file_put_contents($this->root . '/content/drafts/' . $slug . '.md', "---\ntitle: {$title}\n{$meta}---\n\nBody.\n");
    }
}
