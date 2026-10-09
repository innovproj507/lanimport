<?php

declare(strict_types=1);

namespace Courier\Cliente\Domain;

use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class ClienteDocumento
{
    public function __construct(
        private readonly Uuid $id,
        private readonly string $clienteId,
        private readonly string $nombreArchivo,
        private readonly string $rutaArchivo,
        private readonly ?string $tipo,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function crear(
        Uuid $id,
        string $clienteId,
        string $nombreArchivo,
        string $rutaArchivo,
        ?string $tipo,
    ): self {
        return new self($id, $clienteId, $nombreArchivo, $rutaArchivo, $tipo, new DateTimeImmutable());
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function clienteId(): string
    {
        return $this->clienteId;
    }

    public function nombreArchivo(): string
    {
        return $this->nombreArchivo;
    }

    public function rutaArchivo(): string
    {
        return $this->rutaArchivo;
    }

    public function tipo(): ?string
    {
        return $this->tipo;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
