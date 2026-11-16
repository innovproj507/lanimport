<?php

declare(strict_types=1);

namespace Courier\Lpn\Domain;

interface UbicacionRepositoryInterface
{
    public function save(Ubicacion $ubicacion): void;

    /** @return array<int, Ubicacion> */
    public function findAll(): array;

    public function findById(string $id): ?Ubicacion;

    public function findByCodigo(string $codigo): ?Ubicacion;

    /** @return array{items: array<int, Ubicacion>, total: int} */
    public function searchPaginado(string $term, int $page, int $perPage): array;

    /** En la bodega dada (o la activa; si no hay, en cualquiera). */
    public function existsCodigo(string $codigo, ?string $bodegaId = null): bool;
}
