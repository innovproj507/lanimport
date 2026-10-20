<?php

declare(strict_types=1);

namespace Courier\Reempaque\Domain;

use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Orden de reempaque: trafico indica que bultos (LPNs origen) de un cliente se reempacan;
 * bodega la ejecuta abriendo cajas nuevas (LPNs destino), escaneando lo que mete en cada
 * una y cerrandolas. Siempre es de un solo cliente.
 */
final class ReempaqueOrden extends AggregateRoot
{
    /**
     * @param array<int, string> $origenLpnIds
     * @param array<string, array{cerrada: bool, contenido: array<int, string>}> $cajas destinoLpnId => estado y origenes que lleva
     */
    public function __construct(
        private readonly Uuid $id,
        private readonly TipoReempaque $tipo,
        private readonly string $clienteId,
        private EstadoReempaqueOrden $estado,
        private readonly array $origenLpnIds,
        private array $cajas,
        private readonly string $creadoPorUsuarioId,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $completedAt,
        private ?string $codigo = null,
        private ?int $secuencia = null,
        private readonly ?string $instrucciones = null,
    ) {
    }

    /** @param array<int, string> $origenLpnIds */
    public static function iniciar(
        Uuid $id,
        TipoReempaque $tipo,
        string $clienteId,
        array $origenLpnIds,
        string $usuarioId,
        ?string $instrucciones = null,
    ): self {
        if ($origenLpnIds === []) {
            throw new ValidationException('La orden de reempaque debe tener al menos un bulto.');
        }

        $instrucciones = $instrucciones !== null ? trim($instrucciones) : null;

        return new self(
            $id,
            $tipo,
            $clienteId,
            EstadoReempaqueOrden::PENDIENTE,
            array_values(array_unique($origenLpnIds)),
            [],
            $usuarioId,
            new DateTimeImmutable(),
            null,
            null,
            null,
            $instrucciones !== '' ? mb_substr((string) $instrucciones, 0, 500) : null,
        );
    }

    /** Bodega empieza una caja/paleta nueva; solo una abierta a la vez. */
    public function abrirCaja(string $destinoLpnId): void
    {
        $this->assertPendiente();

        if ($this->cajaAbierta() !== null) {
            throw new ValidationException('Ya hay una caja abierta: cierrela antes de abrir otra.');
        }

        $this->cajas[$destinoLpnId] = ['cerrada' => false, 'contenido' => []];
    }

    /** Mete un bulto de la orden en la caja abierta. */
    public function empacar(string $origenLpnId): void
    {
        $this->assertPendiente();
        $abierta = $this->cajaAbierta();

        if ($abierta === null) {
            throw new ValidationException('Abra una caja nueva antes de escanear los bultos.');
        }

        if (!in_array($origenLpnId, $this->origenLpnIds, true)) {
            throw new ValidationException('Ese bulto no es parte de esta orden de reempaque.');
        }

        if (in_array($origenLpnId, $this->cajas[$abierta]['contenido'], true)) {
            throw new ValidationException('Ese bulto ya esta en esta caja.');
        }

        if (!$this->tipo->permiteBultoEnVariasCajas() && $this->cajaDe($origenLpnId) !== null) {
            throw new ValidationException('Ese bulto ya se empaco en otra caja de esta orden.');
        }

        $this->cajas[$abierta]['contenido'][] = $origenLpnId;
    }

    /** Saca un bulto de la caja abierta (escaneado por error). */
    public function quitar(string $origenLpnId): void
    {
        $this->assertPendiente();
        $abierta = $this->cajaAbierta();

        if ($abierta === null || !in_array($origenLpnId, $this->cajas[$abierta]['contenido'], true)) {
            throw new ValidationException('Ese bulto no esta en la caja abierta.');
        }

        $this->cajas[$abierta]['contenido'] = array_values(array_diff($this->cajas[$abierta]['contenido'], [$origenLpnId]));
    }

    /** @return string id del LPN de la caja cerrada */
    public function cerrarCaja(): string
    {
        $this->assertPendiente();
        $abierta = $this->cajaAbierta();

        if ($abierta === null) {
            throw new ValidationException('No hay ninguna caja abierta.');
        }

        if ($this->cajas[$abierta]['contenido'] === []) {
            throw new ValidationException('La caja esta vacia: escanee los bultos que lleva antes de cerrarla.');
        }

        $this->cajas[$abierta]['cerrada'] = true;

        return $abierta;
    }

    /** Descarta la caja abierta vacia (se abrio por error). @return string id del LPN descartado */
    public function descartarCajaVacia(): string
    {
        $this->assertPendiente();
        $abierta = $this->cajaAbierta();

        if ($abierta === null || $this->cajas[$abierta]['contenido'] !== []) {
            throw new ValidationException('Solo se puede descartar una caja abierta y vacia.');
        }

        unset($this->cajas[$abierta]);

        return $abierta;
    }

    /** Todo empacado en cajas cerradas: la orden se da por terminada. */
    public function terminar(): void
    {
        $this->assertPendiente();

        if ($this->cajaAbierta() !== null) {
            throw new ValidationException('Cierre la caja abierta antes de terminar.');
        }

        $faltan = $this->origenesSinEmpacar();

        if ($faltan !== []) {
            throw new ValidationException('Faltan ' . count($faltan) . ' bulto(s) por empacar.');
        }

        $this->estado->assertTransitionTo(EstadoReempaqueOrden::COMPLETADO);
        $this->estado = EstadoReempaqueOrden::COMPLETADO;
        $this->completedAt = new DateTimeImmutable();
    }

    public function cancelar(): void
    {
        $this->estado->assertTransitionTo(EstadoReempaqueOrden::CANCELADO);
        $this->estado = EstadoReempaqueOrden::CANCELADO;
        $this->cajas = [];
    }

    public function cajaAbierta(): ?string
    {
        foreach ($this->cajas as $destinoId => $caja) {
            if (!$caja['cerrada']) {
                return $destinoId;
            }
        }

        return null;
    }

    /** Primera caja (destino) donde va ese bulto, o null si aun no se empaca. */
    public function cajaDe(string $origenLpnId): ?string
    {
        foreach ($this->cajas as $destinoId => $caja) {
            if (in_array($origenLpnId, $caja['contenido'], true)) {
                return $destinoId;
            }
        }

        return null;
    }

    /** @return array<int, string> bultos que todavia no estan en una caja cerrada */
    public function origenesSinEmpacar(): array
    {
        return array_values(array_filter($this->origenLpnIds, function (string $origenId): bool {
            foreach ($this->cajas as $caja) {
                if ($caja['cerrada'] && in_array($origenId, $caja['contenido'], true)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /** @return array{empacados: int, total: int, cajas: int} */
    public function progreso(): array
    {
        $total = count($this->origenLpnIds);

        return [
            'empacados' => $total - count($this->origenesSinEmpacar()),
            'total' => $total,
            'cajas' => count(array_filter($this->cajas, static fn (array $c) => $c['cerrada'])),
        ];
    }

    private function assertPendiente(): void
    {
        if ($this->estado !== EstadoReempaqueOrden::PENDIENTE) {
            throw new ValidationException("La orden de reempaque esta {$this->estado->value}.");
        }
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

    public function instrucciones(): ?string
    {
        return $this->instrucciones;
    }

    /** @return array<int, string> */
    public function origenLpnIds(): array
    {
        return $this->origenLpnIds;
    }

    /** @return array<string, array{cerrada: bool, contenido: array<int, string>}> */
    public function cajas(): array
    {
        return $this->cajas;
    }

    /** @return array<int, string> LPNs de las cajas nuevas, en el orden en que se abrieron */
    public function destinoLpnIds(): array
    {
        return array_keys($this->cajas);
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
