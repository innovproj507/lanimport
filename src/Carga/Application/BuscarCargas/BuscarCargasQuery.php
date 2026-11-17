<?php

declare(strict_types=1);

namespace Courier\Carga\Application\BuscarCargas;

use Courier\Shared\Application\QueryInterface;

final class BuscarCargasQuery implements QueryInterface
{
    /** Pestañas de Ingreso de Carga: las que siguen en bodega, las que ya estan en una DO (o despachadas) o todas. */
    public const VISTA_BODEGA = 'bodega';
    public const VISTA_EN_SALIDA = 'salida';
    public const VISTA_TODAS = 'todas';

    public function __construct(
        public readonly string $termino,
        public readonly ?string $estado = null,
        public readonly int $page = 1,
        public readonly int $perPage = 10,
        /** @var array<int, string>|string|null uno o varios aduaneros */
        public readonly array|string|null $aduaneroIdentificador = null,
        public readonly string $vista = self::VISTA_TODAS,
        /** @var array<int, string> uno o varios clientes (vacio = todos) */
        public readonly array $clienteIds = [],
    ) {
    }
}
