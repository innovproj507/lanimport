<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

/**
 * Devoluciones de carga al proveedor (total o parcial): quien, cuando, motivo, que bultos y sus
 * documentos (factura, nota de credito, guia...). Nunca se borran: son el historial de la carga.
 */
interface DevolucionProveedorRepositoryInterface
{
    /** @param array<int, string> $lpnIds bultos devueltos (vacio si la carga no tenia etiquetas) */
    public function registrar(string $id, string $cargaId, int $bultos, bool $completa, string $motivo, ?string $usuarioId, array $lpnIds): void;

    public function agregarDocumento(string $devolucionId, string $nombre, string $ruta, ?string $usuarioId): void;

    /** Carga a la que pertenece la devolucion (null si no existe). */
    public function cargaIdDe(string $devolucionId): ?string;

    /**
     * Las devoluciones de la carga, la mas reciente primero.
     *
     * @return array<int, array{id: string, fecha: string, bultos: int, completa: bool, motivo: string, usuario: ?string,
     *     etiquetas: array<int, string>, documentos: array<int, array{id: string, nombre: string, ruta: string, fecha: string, usuario: ?string}>}>
     */
    public function listarPorCarga(string $cargaId): array;
}
