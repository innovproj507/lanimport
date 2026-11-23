<?php

declare(strict_types=1);

namespace Courier\Devolucion\Domain;

enum EstadoDevolucion: string
{
    /** Armada: bodega escanea los bultos al subirlos al camion. */
    case PENDIENTE = 'pendiente';
    /** El camion salio: los bultos quedaron devueltos al proveedor. */
    case DESPACHADA = 'despachada';
    case CANCELADA = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente (cargando)',
            self::DESPACHADA => 'Devuelta (salio el camion)',
            self::CANCELADA => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDIENTE => 'amber',
            self::DESPACHADA => 'orange',
            self::CANCELADA => 'slate',
        };
    }
}
