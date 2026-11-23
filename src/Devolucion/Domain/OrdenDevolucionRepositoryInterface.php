<?php

declare(strict_types=1);

namespace Courier\Devolucion\Domain;

interface OrdenDevolucionRepositoryInterface
{
    /** Inserta o actualiza la orden y sus lineas (borra solo las quitadas en esta operacion). */
    public function save(OrdenDevolucion $orden): void;

    /** Lee la orden; dentro de una transaccion la bloquea (FOR UPDATE) hasta el commit. */
    public function findById(string $id): ?OrdenDevolucion;

    /**
     * Ordenes para el listado, las mas recientes primero.
     *
     * @param array<int, string> $estados vacio = todas
     * @return array<int, array{id: string, codigo: string, estado: string, bodegaId: ?string, motivo: string, placa: ?string, empresa: ?string,
     *     cargas: int, clientes: string, proveedores: string, bultos: int, escaneados: int, creada: string, confirmada: ?string}>
     */
    public function listar(array $estados, ?string $bodegaId = null): array;

    /**
     * Bultos de la orden con su carga, cliente y proveedor, para la pantalla de la orden.
     *
     * @return array<int, array{lpnId: string, codigo: string, escaneado: ?string, cargaId: ?string, tracking: string, cliente: string, proveedor: string, aduanero: string, ubicacion: ?string}>
     */
    public function detalleLineas(string $ordenId): array;

    /**
     * Ordenes (no canceladas) en que esta la carga, con cuantos bultos suyos lleva cada una. Incluye
     * las devoluciones directas viejas (sin lineas, ligadas por carga_id).
     *
     * @return array<int, array{id: string, codigo: string, estado: string, fecha: string, motivo: string, bultos: int, usuario: ?string,
     *     documentos: array<int, array{id: string, nombre: string, ruta: string, fecha: string, usuario: ?string}>}>
     */
    public function porCarga(string $cargaId): array;

    /** @return array<int, array{id: string, nombre: string, ruta: string, fecha: string, usuario: ?string}> */
    public function documentos(string $ordenId): array;

    public function agregarDocumento(string $ordenId, string $nombre, string $ruta, ?string $usuarioId): void;
}
