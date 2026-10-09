<?php

declare(strict_types=1);

namespace Courier\Pais\Domain;

interface PaisRepositoryInterface
{
    public function save(Pais $pais): void;

    public function findById(string $id): ?Pais;

    public function findByIdentificador(string $identificador): ?Pais;

    public function findByNombre(string $nombre): ?Pais;

    /** @return array<int, Pais> */
    public function findAll(): array;

    /** @return array{items: array<int, Pais>, total: int} */
    public function searchPaginado(string $term, int $page, int $perPage): array;

    public function existsIdentificador(string $identificador): bool;
}
