<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    public function test_it_reads_nested_values_with_dot_notation(): void
    {
        $config = new Config();
        $config->set('app', ['name' => 'Katakata']);

        $this->assertSame('Katakata', $config->get('app.name'));
        $this->assertNull($config->get('app.missing'));
        $this->assertSame('fallback', $config->get('app.missing', 'fallback'));
    }

    public function test_it_becomes_immutable_once_frozen(): void
    {
        $config = new Config();
        $config->set('app', ['name' => 'Katakata']);
        $config->freeze();

        $this->expectException(RuntimeException::class);
        $config->set('app', ['name' => 'Changed']);
    }
}
