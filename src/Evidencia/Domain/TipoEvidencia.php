<?php

declare(strict_types=1);

namespace Courier\Evidencia\Domain;

/** Momento en que bodega toma la foto: al recibir la carga o al despachar la salida. */
enum TipoEvidencia: string
{
    case Recepcion = 'recepcion';
    case Salida = 'salida';

    public function label(): string
    {
        return match ($this) {
            self::Recepcion => 'Recepcion',
            self::Salida => 'Salida',
        };
    }
}
