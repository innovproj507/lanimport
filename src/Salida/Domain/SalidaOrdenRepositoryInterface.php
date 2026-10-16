<?php

declare(strict_types=1);

namespace Courier\Salida\Domain;

interface SalidaOrdenRepositoryInterface
{
    public function save(SalidaOrden $orden): void;

    public function findById(string $id): ?SalidaOrden;

    /**
     * @param array{estado?: string, clienteId?: string} $filtros
     * @return array<int, SalidaOrden>
     */
    public function search(array $filtros): array;

    /**
     * Filas del manifiesto de la salida: una por carga, con los bultos que salen y sus
     * kilos, m3 y valor (prorrateados si solo sale parte de la carga).
     *
     * @return array<int, array{cliente: string, marca: string, compania: string, tracking: ?string, bultos: int, kilos: float, m3: float, valor: float}>
     */
    public function manifiesto(string $ordenId): array;
}
