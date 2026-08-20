<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Settings;

use Katakata\Editorial\AtomicFile;
use Katakata\Settings\SettingsStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SettingsStoreTest extends TestCase
{
    private string $root;
    private string $path;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katakata-settings-' . bin2hex(random_bytes(6));
        $this->path = $this->root . '/application.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testMissingSettingsReadAsEmptyWithoutCreatingStorage(): void
    {
        $store = $this->store();

        self::assertSame([], $store->all());
        self::assertSame([], $store->section('publication'));
        self::assertDirectoryDoesNotExist($this->root);
    }

    public function testItAtomicallyPersistsOneSectionWithoutChangingOthers(): void
    {
        $store = $this->store();
        $store->updateSection('publication', [
            'name' => 'Katakata',
            'description' => 'Independent writing.',
        ]);
        $store->updateSection('appearance', ['theme' => 'paper']);

        self::assertSame([
            'publication' => [
                'name' => 'Katakata',
                'description' => 'Independent writing.',
            ],
            'appearance' => ['theme' => 'paper'],
        ], $store->all());
        self::assertSame(0600, fileperms($this->path) & 0777);
    }

    public function testItRejectsUnknownSectionsAndKeysBeforeWriting(): void
    {
        $store = $this->store();
        $store->updateSection('publication', ['name' => 'Existing']);
        $before = file_get_contents($this->path);

        try {
            $store->updateSection('publication', ['api_key' => 'secret']);
            self::fail('Unknown keys must be rejected.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('publication.api_key', $error->getMessage());
        }

        self::assertSame($before, file_get_contents($this->path));

        $this->expectException(RuntimeException::class);
        $store->section('unknown');
    }

    public function testAppearanceAcceptsTheButtonStyleKey(): void
    {
        $store = $this->store();
        $store->updateSection('appearance', [
            'theme' => 'default',
            'button_style' => 'pill',
        ]);

        self::assertSame([
            'theme' => 'default',
            'button_style' => 'pill',
        ], $store->section('appearance'));
    }

    public function testItRejectsInvalidPersistedDocuments(): void
    {
        mkdir($this->root, 0775, true);
        file_put_contents($this->path, json_encode([
            'publication' => ['name' => ['not scalar']],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(RuntimeException::class);
        $this->store()->all();
    }

    private function store(): SettingsStore
    {
        return new SettingsStore($this->path, new AtomicFile());
    }
}
