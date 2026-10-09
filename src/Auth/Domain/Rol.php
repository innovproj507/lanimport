<?php

declare(strict_types=1);

namespace Courier\Auth\Domain;

enum Rol: string
{
    case CLIENTE = 'cliente';
    case ADUANERO = 'aduanero';
    case OPERACIONES = 'operaciones';
    case BODEGA = 'bodega';
    case GERENTE = 'gerente';
    case ADMIN = 'admin';
    /** Solo Recepcion de Carga (llenar, corregir e imprimir actas de recepcion). */
    case RECEPCION = 'recepcion';

    /** @return array<int, self> */
    public static function all(): array
    {
        return self::cases();
    }
}
