<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

/**
 * Los bultos (LPNs) guardan el cliente de su carga al generarse. Si en Editar carga se corrige
 * el cliente, hay que pasarlo a sus LPNs y a las salidas donde van, o la salida sigue
 * mostrando el cliente equivocado.
 */
interface ClienteBultosCargaInterface
{
    public function reasignar(string $cargaId, string $clienteId): void;
}
