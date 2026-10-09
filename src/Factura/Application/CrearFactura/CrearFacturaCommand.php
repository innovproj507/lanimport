<?php

declare(strict_types=1);

namespace Courier\Factura\Application\CrearFactura;

use Courier\Shared\Application\CommandInterface;

final class CrearFacturaCommand implements CommandInterface
{
    /** @param array<int, array{descripcion: string, cantidad: float, precioUnitario: float}> $lineas */
    public function __construct(
        public readonly string $clienteId,
        public readonly ?string $cargaId,
        public readonly string $moneda,
        public readonly array $lineas,
        public readonly ?string $notas,
        public readonly string $usuarioId,
    ) {
    }
}
