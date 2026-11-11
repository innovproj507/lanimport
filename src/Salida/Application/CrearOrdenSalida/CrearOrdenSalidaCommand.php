<?php

declare(strict_types=1);

namespace Courier\Salida\Application\CrearOrdenSalida;

use Courier\Shared\Application\CommandInterface;

final class CrearOrdenSalidaCommand implements CommandInterface
{
    /**
     * @param string|null $clienteId si se indica, todos los LPNs deben ser de ese cliente; null = salida
     *                               que puede llevar cargas de varios clientes (consolidada)
     * @param array<int, string> $lpnIds
     * @param array<string, string|null>|null $embarque datos del contenedor (claves de DatosEmbarque::desdeArreglo,
     *                                                  con navieraId en vez de navieraNombre); null = se cargan despues
     * @param bool $generarDo true = trafico arma la salida y recibe el DO al crearla; false = orden de
     *                        salida de inventario, sin DO (trafico lo genera despues al ordenar el contenedor)
     */
    public function __construct(
        public readonly ?string $clienteId,
        public readonly array $lpnIds,
        public readonly ?string $aduaneroIdentificador,
        public readonly string $usuarioId,
        public readonly ?array $embarque = null,
        public readonly bool $generarDo = true,
    ) {
    }
}
