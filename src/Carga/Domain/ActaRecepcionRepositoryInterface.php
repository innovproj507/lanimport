<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

interface ActaRecepcionRepositoryInterface
{
    public function save(ActaRecepcion $acta): void;

    public function findById(string $id): ?ActaRecepcion;

    public function findByCargaId(string $cargaId): ?ActaRecepcion;

    /**
     * Cargas registradas que todavia no tienen acta (excepto las ya entregadas), mas antiguas primero.
     *
     * @return array<int, array{cargaId: string, clienteId: string, trackingNumero: string, clienteNombre: string, proveedor: string, fechaIngreso: string}>
     */
    public function cargasPendientes(): array;

    /**
     * Actas registradas por bodega que aun no tienen carga asociada (esperan el ingreso de operaciones).
     *
     * @return array<int, array{actaId: string, codigo: string, clienteId: string, clienteNombre: string, marca: ?string, totalRecibido: int, entregadoPor: string, fechaRecepcion: string}>
     */
    public function actasSinCarga(): array;

    /**
     * Ultimas actas registradas (con o sin carga).
     *
     * @return array<int, array{actaId: string, codigo: string, trackingNumero: ?string, clienteNombre: string, totalRecibido: int, entregadoPor: string, verificadoPor: string, fechaRecepcion: string}>
     */
    public function actasRecientes(int $limite = 20): array;
}
