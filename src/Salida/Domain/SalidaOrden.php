<?php

declare(strict_types=1);

namespace Courier\Salida\Domain;

use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class SalidaOrden extends AggregateRoot
{
    /** @param array<int, SalidaLinea> $lineas */
    public function __construct(
        private readonly Uuid $id,
        private readonly string $clienteId,
        private readonly ?string $aduaneroIdentificador,
        private EstadoSalidaOrden $estado,
        private readonly string $creadoPorUsuarioId,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $confirmedAt,
        private array $lineas,
        private ?string $codigo = null,
        private ?int $secuencia = null,
    ) {
    }

    /** @param array<int, SalidaLinea> $lineas */
    public static function crear(
        Uuid $id,
        string $clienteId,
        ?string $aduaneroIdentificador,
        array $lineas,
        string $usuarioId,
    ): self {
        if ($lineas === []) {
            throw new ValidationException('La orden de salida debe tener al menos un LPN.');
        }

        return new self(
            $id,
            $clienteId,
            $aduaneroIdentificador,
            EstadoSalidaOrden::PENDIENTE,
            $usuarioId,
            new DateTimeImmutable(),
            null,
            $lineas,
        );
    }

    public function registrarEscaneo(string $codigoLpn): void
    {
        foreach ($this->lineas as $linea) {
            if ($linea->codigoLpn() === $codigoLpn) {
                if (!$linea->escaneado()) {
                    $linea->marcarEscaneado();
                }

                return;
            }
        }

        throw new ValidationException("El LPN {$codigoLpn} no pertenece a esta orden de salida.");
    }

    public function confirmar(string $usuarioId): void
    {
        $pendientes = array_values(array_filter(
            $this->lineas,
            fn (SalidaLinea $linea) => !$linea->escaneado(),
        ));

        if ($pendientes !== []) {
            $codigos = implode(', ', array_map(fn (SalidaLinea $linea) => $linea->codigoLpn(), $pendientes));

            throw new ValidationException("Quedan LPNs sin escanear: {$codigos}.");
        }

        $this->estado->assertTransitionTo(EstadoSalidaOrden::CONFIRMADA);
        $this->estado = EstadoSalidaOrden::CONFIRMADA;
        $this->confirmedAt = new DateTimeImmutable();
    }

    public function cancelar(): void
    {
        $this->estado->assertTransitionTo(EstadoSalidaOrden::CANCELADA);
        $this->estado = EstadoSalidaOrden::CANCELADA;
    }

    /** @return array{escaneadas: int, total: int} */
    public function progreso(): array
    {
        $total = count($this->lineas);
        $escaneadas = count(array_filter($this->lineas, fn (SalidaLinea $linea) => $linea->escaneado()));

        return ['escaneadas' => $escaneadas, 'total' => $total];
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function clienteId(): string
    {
        return $this->clienteId;
    }

    public function aduaneroIdentificador(): ?string
    {
        return $this->aduaneroIdentificador;
    }

    public function estado(): EstadoSalidaOrden
    {
        return $this->estado;
    }

    public function creadoPorUsuarioId(): string
    {
        return $this->creadoPorUsuarioId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function confirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    /** @return array<int, SalidaLinea> */
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
