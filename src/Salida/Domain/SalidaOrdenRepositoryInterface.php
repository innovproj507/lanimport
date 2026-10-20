<?php

declare(strict_types=1);

namespace Courier\Salida\Domain;

interface SalidaOrdenRepositoryInterface
{
    public function save(SalidaOrden $orden): void;

    public function findById(string $id): ?SalidaOrden;

    /**
     * Nombres de los clientes que van en cada salida (una salida puede ser consolidada).
     *
     * @param array<int, string> $ordenIds
     * @return array<string, array<int, string>> ordenId => nombres
     */
    public function clientesPorOrden(array $ordenIds): array;

    /**
     * Avance de la salida agrupado por carga, para que bodega escanee carga por carga.
     *
     * @return array<int, array{cargaId: string, tracking: string, cliente: string, marca: ?string, total: int, escaneadas: int, pendientes: array<int, string>, codigos: array<int, string>}>
     */
    public function avancePorCarga(string $ordenId): array;

    /** Ultima salida confirmada que llevo bultos (LPNs) de la carga, o null si aun no sale. */
    public function ultimaConfirmadaConCarga(string $cargaId): ?SalidaOrden;

    /**
     * @param array{estado?: string, clienteId?: string} $filtros
     * @return array<int, SalidaOrden>
     */
    public function search(array $filtros): array;

    /**
     * Filas del manifiesto de la salida: una por carga, con los bultos que salen y sus
     * kilos, m3, pies3 y valor (prorrateados si solo sale parte de la carga). Tambien la usa
     * el reporte de salida (fecha de ingreso, aduanero y descripcion de cada carga).
     *
     * @return array<int, array{cliente: string, marca: string, compania: string, tracking: ?string, bultos: int, kilos: float, m3: float, pies3: float, valor: float, fechaIngreso: ?string, aduanero: string, descripcion: string}>
     */
    public function manifiesto(string $ordenId): array;
}
