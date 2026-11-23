<?php

declare(strict_types=1);

namespace Courier\Carga\Application\DevolverCargaProveedor;

use Courier\Shared\Application\CommandInterface;

final class DevolverCargaProveedorCommand implements CommandInterface
{
    /**
     * @param array<int, string>|null $lpnIds bultos que se devuelven; null = todos los que estan en bodega
     */
    public function __construct(
        public readonly string $cargaId,
        public readonly ?array $lpnIds,
        public readonly string $motivo,
        public readonly string $usuarioId,
    ) {
    }
}
