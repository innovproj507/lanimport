<?php

declare(strict_types=1);

namespace Courier\Traslado\Application\MoverTraslado;

use Courier\Shared\Application\CommandInterface;

/**
 * Un paso del traslado:
 *   escanear  -> segun el estado, escaneo de salida (bodega origen) o de llegada (bodega destino);
 *   despachar -> sale el camion (los no escaneados se quedan);
 *   cancelar  -> solo antes de salir.
 */
final class MoverTrasladoCommand implements CommandInterface
{
    public function __construct(
        public readonly string $trasladoId,
        public readonly string $accion,
        public readonly string $usuarioId,
        public readonly ?string $codigo = null,
    ) {
    }
}
