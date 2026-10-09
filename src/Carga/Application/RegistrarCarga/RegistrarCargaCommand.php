<?php

declare(strict_types=1);

namespace Courier\Carga\Application\RegistrarCarga;

use Courier\Shared\Application\CommandInterface;

final class RegistrarCargaCommand implements CommandInterface
{
    /**
     * @param array<int, array{
     *     descripcion: string, unidadMedida: string, cantidad: float,
     *     ancho: float, alto: float, largo: float, peso: float, valor: float, marca?: ?string, cubicajePies?: ?float
     * }> $lineas
     */
    public function __construct(
        public readonly string $clienteId,
        public readonly string $proveedor,
        public readonly ?string $proveedorIdentificador,
        public readonly ?string $aduaneroIdentificador,
        public readonly ?string $aduaneroNombre,
        public readonly ?string $numeroContenedor,
        public readonly ?string $tipoServicio,
        public readonly array $detalleServicio,
        public readonly string $registradoPorUsuarioId,
        public readonly string $fechaIngreso,
        public readonly array $lineas = [],
        public readonly ?string $notas = null,
        public readonly ?string $facturaProveedorNombre = null,
        public readonly ?string $facturaProveedorRuta = null,
    ) {
    }
}
