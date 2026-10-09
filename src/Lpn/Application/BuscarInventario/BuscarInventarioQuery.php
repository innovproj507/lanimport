<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\BuscarInventario;

use Courier\Shared\Application\QueryInterface;

final class BuscarInventarioQuery implements QueryInterface
{
    public function __construct(
        public readonly ?string $estado = null,
        public readonly ?string $ubicacionId = null,
        public readonly ?string $clienteId = null,
        public readonly ?string $cargaCodigo = null,
        public readonly ?string $aduaneroIdentificador = null,
    ) {
    }
}
