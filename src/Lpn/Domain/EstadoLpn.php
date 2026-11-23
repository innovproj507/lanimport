<?php

declare(strict_types=1);

namespace Courier\Lpn\Domain;

use Courier\Lpn\Domain\Exception\TransicionEstadoLpnInvalidaException;

enum EstadoLpn: string
{
    case RECEIVING = 'receiving';
    case AVAILABLE = 'available';
    case IN_REPACK = 'in_repack';
    case RESERVED = 'reserved';
    case QUARANTINE = 'quarantine';
    case CONSUMED = 'consumed';
    /** Va de una bodega a otra (traslado): no se puede despachar ni reempacar. */
    case IN_TRANSFER = 'in_transfer';
    /** Se le devolvio al proveedor (el cliente ya no la quiere): sale del inventario, queda en el historial. */
    case RETURNED = 'returned';
    /** En una orden de devolucion al proveedor que aun no sale (bodega lo escanea al subirlo al camion). */
    case IN_RETURN = 'in_return';

    /** @return array<int, self> */
    private static function transitions(self $estado): array
    {
        return match ($estado) {
            self::RECEIVING => [self::AVAILABLE, self::QUARANTINE],
            self::AVAILABLE => [self::IN_REPACK, self::RESERVED, self::QUARANTINE, self::CONSUMED, self::IN_TRANSFER, self::IN_RETURN],
            self::IN_REPACK => [self::CONSUMED, self::AVAILABLE],
            self::RESERVED => [self::CONSUMED, self::AVAILABLE],
            self::QUARANTINE => [self::AVAILABLE, self::CONSUMED],
            self::CONSUMED => [],
            self::IN_TRANSFER => [self::AVAILABLE],
            self::RETURNED => [],
            self::IN_RETURN => [self::RETURNED, self::AVAILABLE],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, self::transitions($this), true);
    }

    public function assertTransitionTo(self $next): void
    {
        if (!$this->canTransitionTo($next)) {
            throw new TransicionEstadoLpnInvalidaException(
                "No se puede pasar de '{$this->value}' a '{$next->value}'."
            );
        }
    }

    public function label(): string
    {
        return match ($this) {
            self::RECEIVING => 'En recepcion',
            self::AVAILABLE => 'Disponible',
            self::IN_REPACK => 'En reempaque',
            self::RESERVED => 'Reservado',
            self::QUARANTINE => 'En cuarentena',
            self::CONSUMED => 'Consumido',
            self::IN_TRANSFER => 'En traslado',
            self::RETURNED => 'Devuelto al proveedor',
            self::IN_RETURN => 'En devolucion',
        };
    }
}
