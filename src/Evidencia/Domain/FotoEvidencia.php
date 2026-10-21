<?php

declare(strict_types=1);

namespace Courier\Evidencia\Domain;

use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Foto de evidencia de la carga de un cliente (todos sus bultos/paletas, desde varios angulos;
 * no una por etiqueta): al recibirla en bodega, o al subirla al contenedor de una salida
 * (entonces lleva tambien la orden). Solo guarda la ruta del archivo; la imagen esta en disco.
 */
final class FotoEvidencia
{
    private function __construct(
        private readonly Uuid $id,
        private readonly TipoEvidencia $tipo,
        private readonly ?string $cargaId,
        private readonly ?string $salidaOrdenId,
        private readonly string $rutaArchivo,
        private readonly ?string $usuarioId,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function deRecepcion(Uuid $id, string $cargaId, string $rutaArchivo, ?string $usuarioId): self
    {
        return new self($id, TipoEvidencia::Recepcion, $cargaId, null, $rutaArchivo, $usuarioId, new DateTimeImmutable());
    }

    public static function deSalida(Uuid $id, string $salidaOrdenId, string $cargaId, string $rutaArchivo, ?string $usuarioId): self
    {
        return new self($id, TipoEvidencia::Salida, $cargaId, $salidaOrdenId, $rutaArchivo, $usuarioId, new DateTimeImmutable());
    }

    public static function reconstituir(
        string $id,
        string $tipo,
        ?string $cargaId,
        ?string $salidaOrdenId,
        string $rutaArchivo,
        ?string $usuarioId,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(new Uuid($id), TipoEvidencia::from($tipo), $cargaId, $salidaOrdenId, $rutaArchivo, $usuarioId, $createdAt);
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function tipo(): TipoEvidencia
    {
        return $this->tipo;
    }

    public function cargaId(): ?string
    {
        return $this->cargaId;
    }

    public function salidaOrdenId(): ?string
    {
        return $this->salidaOrdenId;
    }

    public function rutaArchivo(): string
    {
        return $this->rutaArchivo;
    }

    public function usuarioId(): ?string
    {
        return $this->usuarioId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
