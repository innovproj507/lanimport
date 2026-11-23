<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\CambiarClienteCajas;

use Courier\Carga\Domain\BitacoraCargaInterface;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Domain\EstadoReempaqueOrden;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Cambia a nombre de que cliente quedan las cajas de un reempaque (tambien uno ya terminado).
 * Las cargas no cambian: siguen a nombre de su cliente. Solo mientras ninguna caja esta en una
 * OS/DO o ya salio (la salida se arma con el cliente de la caja).
 */
final class CambiarClienteCajasHandler
{
    public function __construct(
        private readonly ReempaqueOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly ClienteRepositoryInterface $clientes,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    /** @return string nombre del cliente a cuyo nombre quedaron las cajas */
    public function handle(string $ordenId, string $clienteId): string
    {
        $orden = $this->ordenes->findById($ordenId) ?? throw new NotFoundException('Orden de reempaque no encontrada.');

        if ($orden->estado() === EstadoReempaqueOrden::CANCELADO) {
            throw new ValidationException('La orden de reempaque esta cancelada.');
        }

        $cliente = $this->clientes->findById($clienteId) ?? throw new ValidationException('Elija el cliente.');

        foreach ($orden->destinoLpnIds() as $cajaId) {
            $caja = $this->lpns->findById($cajaId);

            if ($caja !== null && !in_array($caja->estado(), [EstadoLpn::IN_REPACK, EstadoLpn::AVAILABLE], true)) {
                throw new ValidationException("La caja {$caja->codigo()} ya esta en una orden de salida o salio: sáquela de la OS/DO antes de cambiarle el cliente.");
            }
        }

        // Mismo cliente de las cargas = sin cambio de cliente.
        $destino = $cliente->id() !== $orden->clienteId() ? $cliente->id() : null;

        $this->unitOfWork->run(fn () => $this->ordenes->cambiarClienteCajas($orden->id(), $destino, $cliente->id()));

        $this->bitacora?->registrarPorLpns(
            $orden->origenLpnIds(),
            'reempaque',
            "Las cajas del reempaque {$orden->codigo()} quedan a nombre de {$cliente->nombreCompleto()}",
        );

        return $cliente->nombreCompleto();
    }
}
