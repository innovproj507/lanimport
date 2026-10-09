<?php

declare(strict_types=1);

namespace Courier\Lpn\Domain;

use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Lpn extends AggregateRoot
{
    public function __construct(
        private readonly Uuid $id,
        private readonly ?string $cargaId,
        private readonly string $clienteId,
        private EstadoLpn $estado,
        private ?string $ubicacionId,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?string $codigo = null,
        private ?int $secuencia = null,
        private readonly ?string $marca = null,
        private readonly ?string $descripcion = null,
        private readonly ?int $ordenEnCarga = null,
    ) {
    }

    public static function generar(
        Uuid $id,
        string $cargaId,
        string $clienteId,
        string $codigo,
        ?string $marca = null,
        ?string $descripcion = null,
        ?int $ordenEnCarga = null,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            $id,
            $cargaId,
            $clienteId,
            EstadoLpn::RECEIVING,
            null,
            $now,
            $now,
            $codigo,
            null,
            $marca,
            $descripcion,
            $ordenEnCarga,
        );
    }

    public static function generarDesdeReempaque(Uuid $id, string $clienteId, ?string $ubicacionId, string $codigo): self
    {
        $now = new DateTimeImmutable();

        return new self($id, null, $clienteId, EstadoLpn::AVAILABLE, $ubicacionId, $now, $now, $codigo);
    }

    public function confirmarRecepcion(?string $ubicacionId = null): void
    {
        $this->estado->assertTransitionTo(EstadoLpn::AVAILABLE);

        $this->estado = EstadoLpn::AVAILABLE;
        $this->ubicacionId = $ubicacionId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function reservarParaSalida(): void
    {
        $this->estado->assertTransitionTo(EstadoLpn::RESERVED);

        $this->estado = EstadoLpn::RESERVED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function confirmarSalida(): void
    {
        $this->estado->assertTransitionTo(EstadoLpn::CONSUMED);

        $this->estado = EstadoLpn::CONSUMED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function liberarReserva(): void
    {
        $this->estado->assertTransitionTo(EstadoLpn::AVAILABLE);

        $this->estado = EstadoLpn::AVAILABLE;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function enviarAReempaque(): void
    {
        $this->estado->assertTransitionTo(EstadoLpn::IN_REPACK);

        $this->estado = EstadoLpn::IN_REPACK;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function consumirPorReempaque(): void
    {
        $this->estado->assertTransitionTo(EstadoLpn::CONSUMED);

        $this->estado = EstadoLpn::CONSUMED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function liberarDeReempaque(): void
    {
        $this->estado->assertTransitionTo(EstadoLpn::AVAILABLE);

        $this->estado = EstadoLpn::AVAILABLE;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function cargaId(): ?string
    {
        return $this->cargaId;
    }

    public function clienteId(): string
    {
        return $this->clienteId;
    }

    public function estado(): EstadoLpn
    {
        return $this->estado;
    }

    public function ubicacionId(): ?string
    {
        return $this->ubicacionId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function codigo(): ?string
    {
        return $this->codigo;
    }

    public function secuencia(): ?int
    {
        return $this->secuencia;
    }

    public function marca(): ?string
    {
        return $this->marca;
    }

    public function descripcion(): ?string
    {
        return $this->descripcion;
    }

    public function ordenEnCarga(): ?int
    {
        return $this->ordenEnCarga;
    }
}
