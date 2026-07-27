<?php

declare(strict_types=1);

namespace Katakata;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;

/**
 * A minimal service container.
 *
 * Katakata deliberately avoids a heavyweight DI framework. This
 * container supports the three things the application actually
 * needs: explicit bindings, singletons, and simple constructor
 * autowiring for classes it hasn't been told about.
 */
class Container
{
    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function __construct()
    {
        // Intentionally empty. Declared explicitly so subclasses
        // (e.g. Application) can safely call parent::__construct().
    }

    public function bind(string $abstract, Closure $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        unset($this->singletons[$abstract], $this->instances[$abstract]);
    }

    public function singleton(string $abstract, Closure $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        $this->singletons[$abstract] = true;
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract])
            || isset($this->bindings[$abstract])
            || class_exists($abstract);
    }

    public function make(string $abstract): mixed
    {
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $object = ($this->bindings[$abstract])($this);

            if (isset($this->singletons[$abstract])) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        }

        return $this->resolve($abstract);
    }

    /**
     * Autowire a concrete class via constructor reflection.
     */
    private function resolve(string $class): object
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Target class [{$class}] does not exist.");
        }

        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException("Target [{$class}] is not instantiable.");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $parameters = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $parameters[] = $this->make($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $parameters[] = $parameter->getDefaultValue();
                continue;
            }

            throw new InvalidArgumentException(
                "Cannot resolve parameter [{$parameter->getName()}] for [{$class}]."
            );
        }

        return $reflection->newInstance(...$parameters);
    }
}
