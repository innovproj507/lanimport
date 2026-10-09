<?php

declare(strict_types=1);

namespace Courier\Pais\Domain;

use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Pais extends AggregateRoot
{
    public function __construct(
        private readonly Uuid $id,
        private readonly string $identificador,
        private string $nombre,
        private bool $mostrarSerieEtiqueta,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function registrar(Uuid $id, string $identificador, string $nombre, bool $mostrarSerieEtiqueta = false): self
    {
        return new self($id, $identificador, $nombre, $mostrarSerieEtiqueta, new DateTimeImmutable());
    }

    public function actualizar(string $nombre, bool $mostrarSerieEtiqueta): void
    {
        $this->nombre = $nombre;
        $this->mostrarSerieEtiqueta = $mostrarSerieEtiqueta;
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

    public function mostrarSerieEtiqueta(): bool
    {
        return $this->mostrarSerieEtiqueta;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
