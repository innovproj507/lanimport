<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class HistorialEstado
{
    public function __construct(
        private readonly Uuid $id,
        private readonly CargaStatus $estado,
        private readonly string $usuarioId,
        private readonly ?string $comentario,
        private readonly DateTimeImmutable $fecha,
    ) {
    }

    public static function registrar(CargaStatus $estado, string $usuarioId, ?string $comentario = null): self
    {
        return new self(Uuid::generate(), $estado, $usuarioId, $comentario, new DateTimeImmutable());
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function estado(): CargaStatus
    {
        return $this->estado;
    }

    public function usuarioId(): string
    {
        return $this->usuarioId;
    }

    public function comentario(): ?string
    {
        return $this->comentario;
    }

    public function fecha(): DateTimeImmutable
    {
        return $this->fecha;
    }
}
