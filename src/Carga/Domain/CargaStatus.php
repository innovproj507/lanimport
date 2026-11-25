<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

use Courier\Carga\Domain\Exception\InvalidEstadoTransitionException;

enum CargaStatus: string
{
    case INGRESADO = 'ingresado';
    case EN_BODEGA = 'en_bodega';
    case EN_TRANSITO = 'en_transito';
    case EN_ADUANA = 'en_aduana';
    case LIBERADO = 'liberado';
    case LISTO_ENTREGA = 'listo_entrega';
    case ENTREGADO = 'entregado';
    case RETENIDO = 'retenido';
    /** Todos sus bultos se devolvieron al proveedor (el cliente ya no la quiso). No se borra: queda en el historial. */
    case DEVUELTO_PROVEEDOR = 'devuelto_proveedor';
    /** Todos sus bultos se reempacaron en cajas que pasaron a un nuevo ingreso (otra carga, otro tracking). */
    case REEMPACADA = 'reempacada';

    /** @return array<int, self> */
    private static function transitions(self $estado): array
    {
        return match ($estado) {
            self::INGRESADO => [self::EN_BODEGA, self::DEVUELTO_PROVEEDOR, self::REEMPACADA],
            self::EN_BODEGA => [self::EN_TRANSITO, self::RETENIDO, self::DEVUELTO_PROVEEDOR, self::REEMPACADA],
            self::EN_TRANSITO => [self::EN_ADUANA],
            self::EN_ADUANA => [self::LIBERADO, self::RETENIDO],
            self::LIBERADO => [self::LISTO_ENTREGA],
            self::LISTO_ENTREGA => [self::ENTREGADO],
            self::RETENIDO => [self::EN_ADUANA, self::DEVUELTO_PROVEEDOR],
            self::ENTREGADO => [],
            self::DEVUELTO_PROVEEDOR => [],
            self::REEMPACADA => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, self::transitions($this), true);
    }

    public function assertTransitionTo(self $next): void
    {
        if (!$this->canTransitionTo($next)) {
            throw new InvalidEstadoTransitionException(
                "No se puede pasar de '{$this->value}' a '{$next->value}'."
            );
        }
    }

    /**
     * Ya salio de bodega completa (todos sus bultos en salidas confirmadas) o va mas adelante.
     * Retenido cuenta como en bodega: no se sabe si se retuvo antes o despues de salir.
     */
    public function despachada(): bool
    {
        return in_array($this, [self::EN_TRANSITO, self::EN_ADUANA, self::LIBERADO, self::LISTO_ENTREGA, self::ENTREGADO], true);
    }

    /** @return array<int, self> */
    public static function despachadas(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $e) => $e->despachada()));
    }

    /** Todavia en bodega: se le pueden devolver bultos al proveedor. */
    public function permiteDevolucionProveedor(): bool
    {
        return in_array($this, [self::INGRESADO, self::EN_BODEGA, self::RETENIDO], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::INGRESADO => 'Ingresado',
            self::EN_BODEGA => 'En bodega',
            self::EN_TRANSITO => 'En transito',
            self::EN_ADUANA => 'En aduana',
            self::LIBERADO => 'Liberado',
            self::LISTO_ENTREGA => 'Listo para retiro',
            self::ENTREGADO => 'Entregado',
            self::RETENIDO => 'Retenido',
            self::DEVUELTO_PROVEEDOR => 'Devuelta al proveedor',
            self::REEMPACADA => 'Reempacada (nuevo ingreso)',
        };
    }
}
