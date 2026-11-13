<?php

declare(strict_types=1);

namespace Courier\Salida\Domain;

/**
 * Lectura para la pantalla de Rastreo (personal interno): datos de la carga, donde esta cada
 * bulto (ubicacion, caja de reempaque, OS/DO en que va y si ya se escaneo) y todas las ordenes
 * de salida en que ha estado, incluidas las canceladas.
 */
interface RastreoCargaInterface
{
    /**
     * @return array{
     *     carga: array{cliente: string, aduanero: string, proveedor: string, registradoPor: ?string, fechaIngreso: ?string,
     *         contenedor: ?string, notas: ?string},
     *     ordenes: array<int, array{id: string, etiqueta: string, esOs: bool, estado: string, contenedor: ?string, sello: ?string,
     *         naviera: ?string, barco: ?string, fechaCargue: ?string, etd: ?string, destino: ?string, nota: ?string,
     *         creada: string, confirmada: ?string, bultos: int, escaneados: int}>,
     *     bultos: array<int, array{codigo: string, estado: string, ubicacion: ?string, caja: ?string, ordenId: ?string,
     *         orden: ?string, ordenEstado: ?string, escaneado: ?bool}>
     * }
     */
    public function detalle(string $cargaId): array;

    /**
     * Todo lo que le ha pasado a la carga, del mas reciente al mas antiguo: cambios de estado,
     * bitacora (OS/DO, reempaque, etiquetas, ediciones), escaneos de recepcion y de salida,
     * salidas confirmadas, fotos y avisos al cliente.
     *
     * @return array<int, array{fecha: string, tipo: string, titulo: string, detalle: ?string, usuario: ?string}>
     */
    public function movimientos(string $cargaId): array;

    /**
     * Carga eliminada con ese tracking (completo o sus 6 caracteres finales), archivada antes de borrarla, o null.
     *
     * @return array<string, mixed>|null
     */
    public function eliminada(string $trackingNumero): ?array;
}
