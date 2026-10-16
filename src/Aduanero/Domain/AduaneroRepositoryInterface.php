<?php

declare(strict_types=1);

namespace Courier\Aduanero\Domain;

interface AduaneroRepositoryInterface
{
    public function save(Aduanero $aduanero): void;

    public function findById(string $id): ?Aduanero;

    public function findByIdentificador(string $identificador): ?Aduanero;

    /** @return array<int, Aduanero> */
    public function findAllActivos(): array;

    /** @return array{items: array<int, Aduanero>, total: int} */
    public function searchPaginado(string $term, int $page, int $perPage): array;

    public function existsIdentificador(string $identificador): bool;

    /** @return array<string, int> donde se usa (solo lo que tiene registros), ej. ['cargas' => 3] */
    public function referencias(string $id): array;

    public function delete(string $id): void;
}
