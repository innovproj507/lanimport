<?php

declare(strict_types=1);

namespace Courier\Reempaque\Domain;

use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class ReempaqueOrden extends AggregateRoot
{
    /**
     * @param array<int, string> $origenLpnIds
     * @param array<int, string> $destinoLpnIds
     */
    public function __construct(
        private readonly Uuid $id,
        private readonly TipoReempaque $tipo,
        private readonly string $clienteId,
        private EstadoReempaqueOrden $estado,
        private readonly array $origenLpnIds,
        private array $destinoLpnIds,
        private readonly string $creadoPorUsuarioId,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $completedAt,
        private ?string $codigo = null,
        private ?int $secuencia = null,
    ) {
    }

    /** @param array<int, string> $origenLpnIds */
    public static function iniciar(
        Uuid $id,
        TipoReempaque $tipo,
        string $clienteId,
        array $origenLpnIds,
        string $usuarioId,
    ): self {
        if ($origenLpnIds === []) {
            throw new ValidationException('La orden de reempaque debe tener al menos un LPN origen.');
        }

        return new self(
            $id,
            $tipo,
            $clienteId,
            EstadoReempaqueOrden::PENDIENTE,
            $origenLpnIds,
            [],
            $usuarioId,
            new DateTimeImmutable(),
            null,
        );
    }

    /** @param array<int, string> $destinoLpnIds */
    public function completar(array $destinoLpnIds): void
    {
        if ($destinoLpnIds === []) {
            throw new ValidationException('La orden de reempaque debe generar al menos un LPN destino.');
        }

        $this->estado->assertTransitionTo(EstadoReempaqueOrden::COMPLETADO);

        $this->destinoLpnIds = $destinoLpnIds;
        $this->estado = EstadoReempaqueOrden::COMPLETADO;
        $this->completedAt = new DateTimeImmutable();
    }

    public function cancelar(): void
    {
        $this->estado->assertTransitionTo(EstadoReempaqueOrden::CANCELADO);
        $this->estado = EstadoReempaqueOrden::CANCELADO;
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function tipo(): TipoReempaque
    {
        return $this->tipo;
    }

    public function clienteId(): string
    {
        return $this->clienteId;
    }

    public function estado(): EstadoReempaqueOrden
    {
        return $this->estado;
    }

    /** @return array<int, string> */
    public function origenLpnIds(): array
    {
        return $this->origenLpnIds;
    }

    /** @return array<int, string> */
    public function destinoLpnIds(): array
    {
        return $this->destinoLpnIds;
    }

    public function creadoPorUsuarioId(): string
    {
        return $this->creadoPorUsuarioId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function codigo(): ?string
    {
        return $this->codigo;
    }

    public function secuencia(): ?int
    {
        return $this->secuencia;
    }
}
