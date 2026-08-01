<?php

declare(strict_types=1);

namespace Katakata\Tests\Feature;

use Katakata\Tests\Unit\Import\DocxFixture;
use PHPUnit\Framework\TestCase;

final class ImportCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-import-command-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/incoming/nested', 0775, true);
        DocxFixture::minimal($this->root . '/incoming/document.docx', 'Command document', '');
        DocxFixture::minimal($this->root . '/incoming/nested/nested.docx', 'Nested command document');
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testDocumentCommandProducesADryRunPreview(): void
    {
        [$code, $output] = $this->command([
            'import:document',
            $this->root . '/incoming/document.docx',
            '--author=Command author',
            '--dry-run',
        ]);

        self::assertSame(0, $code, $output);
        self::assertStringContainsString('Previewed document.', $output);
        self::assertStringContainsString('Title: Command document', $output);
        self::assertStringContainsString('Author: Command author (low)', $output);
        self::assertStringContainsString('source_file: document.docx', $output);
    }

    public function testDirectoryCommandSupportsRecursiveDryRunsWithSeparateCounts(): void
    {
        [$code, $output] = $this->command([
            'import:directory',
            $this->root . '/incoming',
            '--recursive',
            '--dry-run',
        ]);

        self::assertSame(0, $code, $output);
        self::assertStringContainsString('Imported: 0', $output);
        self::assertStringContainsString('Previewed: 2', $output);
        self::assertStringContainsString('Failed: 0', $output);
    }

    /** @param list<string> $arguments @return array{int,string} */
    private function command(array $arguments): array
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/katakata')
            . ' ' . implode(' ', array_map(escapeshellarg(...), $arguments))
            . ' 2>&1';
        exec($command, $lines, $code);
        return [$code, implode("\n", $lines)];
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $target = $path . '/' . $entry;
            is_dir($target) ? $this->remove($target) : unlink($target);
        }
        rmdir($path);
    }
}
