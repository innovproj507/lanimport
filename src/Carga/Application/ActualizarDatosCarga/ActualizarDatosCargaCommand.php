<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ActualizarDatosCarga;

use Courier\Shared\Application\CommandInterface;

final class ActualizarDatosCargaCommand implements CommandInterface
{
    /**
     * @param array<int, array{
     *     descripcion: string, unidadMedida: string, cantidad: float,
     *     ancho: float, alto: float, largo: float, peso: float, valor: float, marca?: ?string
     * }> $lineas
     */
    public function __construct(
        public readonly string $cargaId,
        public readonly string $clienteId,
        public readonly string $proveedor,
        public readonly ?string $proveedorIdentificador,
        public readonly ?string $aduaneroIdentificador,
        public readonly ?string $aduaneroNombre,
        public readonly ?string $numeroContenedor,
        public readonly string $fechaIngreso,
        public readonly array $lineas,
        public readonly ?string $notas,
        public readonly ?string $facturaProveedorNombre,
        public readonly ?string $facturaProveedorRuta,
    ) {
    }
}
