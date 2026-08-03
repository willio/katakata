<?php

declare(strict_types=1);

namespace Katakata\Backup;

use DateTimeImmutable;
use FilesystemIterator;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class BackupManager
{
    /** @param array<string, string> $sources */
    public function __construct(
        private readonly string $backupPath,
        private readonly array $sources,
    ) {
    }

    /** @return array{path:string,checksum:string,files:int,bytes:int,created_at:string} */
    public function create(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $this->ensureDirectory($this->backupPath);

        $stamp = $now->format('Ymd-His');
        $base = $this->backupPath . DIRECTORY_SEPARATOR . "katakata-{$stamp}";
        $tarPath = $base . '.tar';
        $gzipPath = $tarPath . '.gz';

        if (is_file($tarPath) || is_file($gzipPath)) {
            throw new RuntimeException("Backup already exists for timestamp [{$stamp}].");
        }

        $files = [];
        $bytes = 0;

        try {
            $archive = new PharData($tarPath);

            foreach ($this->sources as $label => $source) {
                if (!is_dir($source) && !is_file($source)) {
                    continue;
                }

                foreach ($this->sourceFiles($source) as $absolute => $relative) {
                    if ($this->isBackupFile($absolute)) {
                        continue;
                    }

                    $archivePath = trim($label, '/\\') . '/' . ltrim($relative, '/\\');
                    $archive->addFile($absolute, $archivePath);
                    $size = filesize($absolute);
                    $size = is_int($size) ? $size : 0;
                    $bytes += $size;
                    $files[] = [
                        'path' => $archivePath,
                        'bytes' => $size,
                        'sha256' => hash_file('sha256', $absolute),
                    ];
                }
            }

            $manifest = [
                'format' => 1,
                'created_at' => $now->format(DateTimeImmutable::ATOM),
                'files' => $files,
                'file_count' => count($files),
                'bytes' => $bytes,
            ];
            $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $archive->addFromString('manifest.json', $encoded);
            $archive->compress(Phar::GZ);
            unset($archive);

            if (!is_file($gzipPath)) {
                throw new RuntimeException('Backup compression did not produce an archive.');
            }

            $this->setPrivateMode($gzipPath, 0600);
            @unlink($tarPath);
            $checksum = hash_file('sha256', $gzipPath);
            if (!is_string($checksum)) {
                throw new RuntimeException('Unable to checksum backup archive.');
            }

            $checksumPath = $gzipPath . '.sha256';
            if (file_put_contents($checksumPath, $checksum . '  ' . basename($gzipPath) . "\n", LOCK_EX) === false) {
                throw new RuntimeException('Unable to write backup checksum.');
            }
            $this->setPrivateMode($checksumPath, 0600);

            return [
                'path' => $gzipPath,
                'checksum' => $checksum,
                'files' => count($files),
                'bytes' => $bytes,
                'created_at' => $manifest['created_at'],
            ];
        } catch (\Throwable $error) {
            @unlink($tarPath);
            @unlink($gzipPath);
            @unlink($gzipPath . '.sha256');
            throw new RuntimeException('Backup creation failed: ' . $error->getMessage(), 0, $error);
        }
    }

    /** @return list<array{path:string,bytes:int,modified_at:string,verified:bool}> */
    public function all(): array
    {
        if (!is_dir($this->backupPath)) {
            return [];
        }

        $backups = [];
        foreach (glob($this->backupPath . DIRECTORY_SEPARATOR . 'katakata-*.tar.gz') ?: [] as $path) {
            $size = filesize($path);
            $modified = filemtime($path);
            $backups[] = [
                'path' => $path,
                'bytes' => is_int($size) ? $size : 0,
                'modified_at' => date(DateTimeImmutable::ATOM, is_int($modified) ? $modified : time()),
                'verified' => $this->verify($path)['valid'],
            ];
        }

        usort($backups, static fn (array $a, array $b): int => strcmp($b['path'], $a['path']));
        return $backups;
    }

    /** @return array{valid:bool,message:string,files:int} */
    public function verify(string $path): array
    {
        if (!is_file($path)) {
            return ['valid' => false, 'message' => "Backup not found [{$path}].", 'files' => 0];
        }

        $checksumPath = $path . '.sha256';
        if (!is_file($checksumPath)) {
            return ['valid' => false, 'message' => 'Checksum sidecar is missing.', 'files' => 0];
        }

        $expected = strtok(trim((string) file_get_contents($checksumPath)), " \t");
        $actual = hash_file('sha256', $path);
        if (!is_string($expected) || !is_string($actual) || !hash_equals($expected, $actual)) {
            return ['valid' => false, 'message' => 'Archive checksum does not match.', 'files' => 0];
        }

        $temporaryBase = tempnam(sys_get_temp_dir(), 'katakata-verify-');
        if (!is_string($temporaryBase)) {
            return ['valid' => false, 'message' => 'Unable to create archive verification workspace.', 'files' => 0];
        }

        @unlink($temporaryBase);
        $temporaryGzip = $temporaryBase . '.tar.gz';
        $temporaryTar = $temporaryBase . '.tar';

        try {
            if (!copy($path, $temporaryGzip)) {
                throw new RuntimeException('Unable to copy archive for verification.');
            }

            $compressed = new PharData($temporaryGzip);
            $compressed->decompress();
            unset($compressed);

            $archive = new PharData($temporaryTar);
            if (!isset($archive['manifest.json'])) {
                return ['valid' => false, 'message' => 'Archive manifest is missing.', 'files' => 0];
            }

            $manifest = json_decode($archive['manifest.json']->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $entries = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
            foreach ($entries as $entry) {
                $name = is_array($entry) ? ($entry['path'] ?? null) : null;
                if (!is_string($name) || !isset($archive[$name])) {
                    return ['valid' => false, 'message' => "Manifest entry is missing [{$name}].", 'files' => count($entries)];
                }

                $content = $archive[$name]->getContent();
                $hash = is_array($entry) ? ($entry['sha256'] ?? '') : '';
                if (!is_string($hash) || !hash_equals($hash, hash('sha256', $content))) {
                    return ['valid' => false, 'message' => "Manifest checksum failed [{$name}].", 'files' => count($entries)];
                }
            }

            return ['valid' => true, 'message' => 'Backup archive and manifest are valid.', 'files' => count($entries)];
        } catch (\Throwable $error) {
            return ['valid' => false, 'message' => 'Unable to read archive: ' . $error->getMessage(), 'files' => 0];
        } finally {
            @unlink($temporaryTar);
            @unlink($temporaryGzip);
        }
    }

    /** @return iterable<string, string> */
    private function sourceFiles(string $source): iterable
    {
        if (is_file($source)) {
            yield $source => basename($source);
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        $prefix = rtrim($source, '/\\') . DIRECTORY_SEPARATOR;
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }

            yield $file->getPathname() => substr($file->getPathname(), strlen($prefix));
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException("Unable to create backup directory [{$path}].");
        }
        $this->setPrivateMode($path, 0700);
        if (!is_writable($path)) {
            throw new RuntimeException("Backup directory is not writable [{$path}].");
        }
    }

    private function setPrivateMode(string $path, int $mode): void
    {
        if (!chmod($path, $mode)) {
            throw new RuntimeException("Unable to set private mode on [{$path}].");
        }
    }

    private function isBackupFile(string $path): bool
    {
        $backupRoot = realpath($this->backupPath);
        $file = realpath($path);
        return is_string($backupRoot) && is_string($file) && str_starts_with($file, $backupRoot . DIRECTORY_SEPARATOR);
    }
}
