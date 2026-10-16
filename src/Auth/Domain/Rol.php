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

    /** Nombre que ve el usuario (el valor interno se conserva por compatibilidad con la BD y la API). */
    public function label(): string
    {
        return match ($this) {
            self::CLIENTE => 'Cliente',
            self::ADUANERO => 'Aduanero',
            self::OPERACIONES => 'Trafico',
            self::BODEGA => 'Bodega',
            self::GERENTE => 'Gerencia',
            self::ADMIN => 'Owner',
        };
    }

    /** @return array<int, self> */
    public static function all(): array
    {
        return self::cases();
    }
}
