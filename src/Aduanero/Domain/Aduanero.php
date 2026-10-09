<?php

declare(strict_types=1);

namespace Courier\Aduanero\Domain;

use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Aduanero extends AggregateRoot
{
    public function __construct(
        private readonly Uuid $id,
        private readonly string $identificador,
        private string $nombre,
        private ?string $pais,
        private readonly ?string $usuarioId,
        private bool $activo,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function registrar(Uuid $id, string $nombre, ?string $pais, ?string $usuarioId = null): self
    {
        return new self($id, '', $nombre, $pais, $usuarioId, true, new DateTimeImmutable());
    }

    public function actualizar(string $nombre, ?string $pais): void
    {
        $this->nombre = $nombre;
        $this->pais = $pais;
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

    public function pais(): ?string
    {
        return $this->pais;
    }

    public function usuarioId(): ?string
    {
        return $this->usuarioId;
    }

    public function activo(): bool
    {
        return $this->activo;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
