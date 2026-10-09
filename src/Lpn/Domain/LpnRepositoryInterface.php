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
}
