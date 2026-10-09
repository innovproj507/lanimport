<?php

declare(strict_types=1);

namespace Courier\Proveedor\Domain;

interface ProveedorRepositoryInterface
{
    public function save(Proveedor $proveedor): void;

    public function findById(string $id): ?Proveedor;

    public function findByIdentificador(string $identificador): ?Proveedor;

    /** @return array<int, Proveedor> */
    public function findAll(): array;

    /** @return array{items: array<int, Proveedor>, total: int} */
    public function searchPaginado(string $term, int $page, int $perPage): array;

    public function existsIdentificador(string $identificador): bool;
}
