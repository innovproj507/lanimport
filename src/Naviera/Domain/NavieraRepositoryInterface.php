<?php

declare(strict_types=1);

namespace Courier\Naviera\Domain;

interface NavieraRepositoryInterface
{
    public function save(Naviera $naviera): void;

    public function findById(string $id): ?Naviera;

    /** @return array<int, Naviera> ordenadas por nombre */
    public function findAll(bool $soloActivas = false): array;

    /** @return array{items: array<int, Naviera>, total: int} */
    public function searchPaginado(string $term, int $page, int $perPage): array;

    public function existsNombre(string $nombre, ?string $excluirId = null): bool;

    /** @return array<string, int> donde se usa (solo las que tienen registros), ej. ['salidas' => 2] */
    public function referencias(string $id): array;

    public function delete(string $id): void;
}
