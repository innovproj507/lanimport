<?php

declare(strict_types=1);

namespace Courier\Factura\Domain;

use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Factura extends AggregateRoot
{
    /** @param array<int, LineaFactura> $lineas */
    public function __construct(
        private readonly Uuid $id,
        private readonly string $clienteId,
        private readonly ?string $cargaId,
        private EstadoFactura $estado,
        private readonly string $moneda,
        private readonly ?string $notas,
        private readonly string $creadoPorUsuarioId,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $paidAt,
        private array $lineas,
        private ?string $codigo = null,
        private ?int $secuencia = null,
    ) {
    }

    /** @param array<int, LineaFactura> $lineas */
    public static function crear(
        Uuid $id,
        string $clienteId,
        ?string $cargaId,
        string $moneda,
        array $lineas,
        ?string $notas,
        string $usuarioId,
    ): self {
        if ($lineas === []) {
            throw new ValidationException('La factura debe tener al menos una linea.');
        }

        foreach ($lineas as $linea) {
            if (!$linea instanceof LineaFactura) {
                throw new ValidationException('Cada linea de factura debe ser una instancia de LineaFactura.');
            }

            if ($linea->cantidad() <= 0) {
                throw new ValidationException("La cantidad de la linea '{$linea->descripcion()}' debe ser mayor a 0.");
            }

            if ($linea->precioUnitario() < 0) {
                throw new ValidationException("El precio unitario de la linea '{$linea->descripcion()}' no puede ser negativo.");
            }
        }

        return new self(
            $id,
            $clienteId,
            $cargaId,
            EstadoFactura::PENDIENTE,
            $moneda,
            $notas,
            $usuarioId,
            new DateTimeImmutable(),
            null,
            $lineas,
        );
    }

    public function total(): float
    {
        return round(array_sum(array_map(fn (LineaFactura $linea) => $linea->subtotal(), $this->lineas)), 2);
    }

    public function marcarPagada(): void
    {
        $this->estado->assertTransitionTo(EstadoFactura::PAGADA);
        $this->estado = EstadoFactura::PAGADA;
        $this->paidAt = new DateTimeImmutable();
    }

    public function anular(): void
    {
        $this->estado->assertTransitionTo(EstadoFactura::ANULADA);
        $this->estado = EstadoFactura::ANULADA;
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function clienteId(): string
    {
        return $this->clienteId;
    }

    public function cargaId(): ?string
    {
        return $this->cargaId;
    }

    public function estado(): EstadoFactura
    {
        return $this->estado;
    }

    public function moneda(): string
    {
        return $this->moneda;
    }

    public function notas(): ?string
    {
        return $this->notas;
    }

    public function creadoPorUsuarioId(): string
    {
        return $this->creadoPorUsuarioId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function paidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    /** @return array<int, LineaFactura> */
    public function lineas(): array
    {
        return $this->lineas;
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
