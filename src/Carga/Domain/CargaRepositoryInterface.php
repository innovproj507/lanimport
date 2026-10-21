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

    /**
     * Lista de cargas (Ingreso de Carga) con filtros, y los totales de todas las cargas filtradas
     * (no solo de la pagina): bultos, peso (kg), cubicaje (p3) y valor de sus lineas.
     *
     * @return array{items: array<int, Carga>, total: int, resumen: array{bultos: float, peso: float, pies3: float, valor: float}}
     */
    public function searchPaginado(string $term, ?string $estado, int $page, int $perPage, ?string $aduaneroIdentificador = null): array;
}
