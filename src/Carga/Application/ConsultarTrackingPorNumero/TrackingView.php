<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ConsultarTrackingPorNumero;

final class TrackingView
{
    /**
     * @param array<int, array{estado: string, estadoLabel: string, fecha: string, comentario: ?string}> $historial
     * @param array<int, array{
     *     descripcion: string, unidadMedida: string, cantidad: float,
     *     ancho: float, alto: float, largo: float, cubicajePies: float, peso: float, valor: float, marca: ?string
     * }> $lineas
     * @param array<int, array{estado: string, estadoLabel: string, cantidad: int}> $bultosPorEstado
     */
    public function __construct(
        public readonly string $cargaId,
        public readonly string $trackingNumero,
        public readonly string $estadoActual,
        public readonly string $estadoActualLabel,
        public readonly ?string $tipoServicio,
        public readonly string $fechaIngreso,
        public readonly ?string $fechaEstimadaLlegada,
        public readonly ?string $numeroContenedor,
        public readonly array $historial,
        public readonly array $lineas = [],
        public readonly ?string $notas = null,
        public readonly ?string $facturaProveedorNombre = null,
        public readonly ?string $facturaProveedorRuta = null,
        public readonly array $bultosPorEstado = [],
        public readonly ?string $proveedor = null,
    ) {
    }
}
