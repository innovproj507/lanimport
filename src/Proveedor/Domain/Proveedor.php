<?php

declare(strict_types=1);

namespace Courier\Proveedor\Domain;

use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Proveedor extends AggregateRoot
{
    public function __construct(
        private readonly Uuid $id,
        private readonly string $identificador,
        private string $nombre,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function registrar(Uuid $id, string $identificador, string $nombre): self
    {
        return new self($id, $identificador, $nombre, new DateTimeImmutable());
    }

    public function actualizar(string $nombre): void
    {
        $this->nombre = $nombre;
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function identificador(): string
    {
        return $this->identificador;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
