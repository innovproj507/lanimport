<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

/**
 * Cuantos bultos (LPNs) de una carga hay, cuantos escaneo bodega y cuantos salieron
 * en salidas confirmadas. Lo usa Carga::sincronizarConBultos() para mover el estado.
 */
interface ConteoBultosCargaInterface
{
    /** @return array{total: int, recibidos: int, despachados: int} */
    public function contar(string $cargaId): array;
}
