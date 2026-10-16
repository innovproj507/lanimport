<?php

declare(strict_types=1);

namespace Courier\Lpn\Domain;

interface LpnRepositoryInterface
{
    public function save(Lpn $lpn): void;

    public function findById(string $id): ?Lpn;

    public function findByCodigo(string $codigo): ?Lpn;

    /** @return array<int, Lpn> */
    public function findByCargaId(string $cargaId): array;

    public function existsLpnsParaCarga(string $cargaId): bool;

    public function deleteByCargaId(string $cargaId): void;

    /**
     * @param array{estado?: string, ubicacionId?: string, clienteId?: string, cargaCodigo?: string} $filtros
     * @return array<int, Lpn>
     */
    public function search(array $filtros): array;

    /**
     * Avance de recepcion por carga: solo cargas con al menos un LPN todavia "En recepcion"
     * (o solo la carga indicada). Mas antiguas primero.
     *
     * @return array<int, array{cargaId: string, tracking: string, cliente: string, marca: ?string, fechaIngreso: string, total: int, recibidos: int}>
     */
    public function resumenRecepcion(?string $cargaId = null): array;
}
