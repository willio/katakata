<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Container;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContainerTest extends TestCase
{
    public function test_it_binds_and_resolves_a_closure(): void
    {
        $container = new Container();
        $container->bind('greeting', fn (): string => 'hello');

        $this->assertSame('hello', $container->make('greeting'));
    }

    public function test_singleton_returns_the_same_instance(): void
    {
        $container = new Container();
        $container->singleton(stdClass::class, fn (): stdClass => new stdClass());

        $this->assertSame($container->make(stdClass::class), $container->make(stdClass::class));
    }

    public function test_it_autowires_a_class_with_no_constructor(): void
    {
        $container = new Container();

        $this->assertInstanceOf(stdClass::class, $container->make(stdClass::class));
    }

    public function test_instance_is_returned_verbatim(): void
    {
        $container = new Container();
        $object = new stdClass();
        $container->instance('thing', $object);

        $this->assertSame($object, $container->make('thing'));
    }
}
