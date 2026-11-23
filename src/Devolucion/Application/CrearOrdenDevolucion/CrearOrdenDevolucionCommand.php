<?php

declare(strict_types=1);

namespace Courier\Devolucion\Application\CrearOrdenDevolucion;

use Courier\Devolucion\Domain\DatosTransporte;
use Courier\Shared\Application\CommandInterface;

final class CrearOrdenDevolucionCommand implements CommandInterface
{
    /** @param array<int, string> $lpnIds bultos disponibles de la bodega que se devuelven */
    public function __construct(
        public readonly string $bodegaId,
        public readonly array $lpnIds,
        public readonly string $motivo,
        public readonly ?string $nota,
        public readonly DatosTransporte $transporte,
        public readonly string $usuarioId,
    ) {
    }
}
