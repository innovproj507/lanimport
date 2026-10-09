<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

use Courier\Carga\Domain\ValueObject\Contenedor;
use Courier\Carga\Domain\ValueObject\TrackingNumero;

interface CargaRepositoryInterface
{
    public function save(Carga $carga): void;

    /** @return array<int, Carga> */
    public function findAll(): array;

    public function findById(string $id): ?Carga;

    public function findByTrackingNumero(TrackingNumero $numero): ?Carga;

    /** @return array<int, Carga> */
    public function findByContenedor(Contenedor $contenedor): array;

    /** @return array<int, Carga> */
    public function findByClienteId(string $clienteId): array;

    public function existsTrackingNumero(TrackingNumero $numero): bool;

    /** @return array{items: array<int, Carga>, total: int} */
    public function searchPaginado(string $term, ?string $estado, int $page, int $perPage): array;
}
