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

    /** La carga mas reciente cuyo tracking termina en esos 6 caracteres (LAN-AAAA-XXXXXX). */
    public function findByTrackingSufijo(string $sufijo): ?Carga;

    /** @return array<int, Carga> */
    public function findByContenedor(Contenedor $contenedor): array;

    /** @return array<int, Carga> */
    public function findByClienteId(string $clienteId): array;

    public function existsTrackingNumero(TrackingNumero $numero): bool;

    /** Borra la carga; lineas, detalle, historial y etiquetas se borran en cascada. */
    public function eliminar(string $id): void;

    /**
     * Lista de cargas (Ingreso de Carga) con filtros, y los totales de todas las cargas filtradas
     * (no solo de la pagina): bultos, peso (kg), cubicaje (p3) y valor de sus lineas.
     *
     * @return array{items: array<int, Carga>, total: int, resumen: array{bultos: float, peso: float, pies3: float, valor: float}}
     */
    /**
     * @param array<int, string>|string|null $aduaneroIdentificador uno o varios (vacio = todos)
     * @param array<int, string> $clienteIds uno o varios (vacio = todos)
     */
    public function searchPaginado(string $term, ?string $estado, int $page, int $perPage, array|string|null $aduaneroIdentificador = null, ?bool $enSalida = null, array $clienteIds = []): array;
}
