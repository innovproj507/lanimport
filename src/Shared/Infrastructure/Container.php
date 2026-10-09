<?php

declare(strict_types=1);

namespace Courier\Shared\Infrastructure;

final class Container
{
    /** @var array<string, callable> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("No hay binding registrado para: {$id}");
        }

        $instance = ($this->factories[$id])($this);
        $this->instances[$id] = $instance;

        return $instance;
    }
}
