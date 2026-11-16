<?php

declare(strict_types=1);

namespace Courier\Traslado\Domain;

enum EstadoTraslado: string
{
    case PENDIENTE = 'pendiente';
    case EN_CAMINO = 'en_camino';
    case RECIBIDO = 'recibido';
    case CANCELADO = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Por despachar',
            self::EN_CAMINO => 'En camino',
            self::RECIBIDO => 'Recibido',
            self::CANCELADO => 'Cancelado',
        };
    }
}
