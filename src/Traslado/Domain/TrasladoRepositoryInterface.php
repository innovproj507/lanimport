<?php

declare(strict_types=1);

namespace Courier\Traslado\Domain;

interface TrasladoRepositoryInterface
{
    public function save(Traslado $traslado): void;

    public function findById(string $id): ?Traslado;

    /**
     * Traslados que salen de o llegan a la bodega activa (todas si no hay).
     *
     * @param array<int, string> $estados vacio = todos
     * @return array<int, Traslado>
     */
    public function listar(array $estados = []): array;

    /**
     * Lineas para mostrar, con su carga y cliente.
     *
     * @return array<int, array{codigo: string, cargaId: ?string, tracking: string, cliente: string, salida: ?string, llegada: ?string}>
     */
    public function detalleLineas(string $trasladoId): array;

    /** Traslados en camino hacia una bodega (para avisar en Recepcion). */
    public function enCaminoHacia(string $bodegaId): int;

    /** Traslado abierto (pendiente o en camino) en que va un bulto, o null. */
    public function abiertoConLpn(string $lpnId): ?Traslado;
}
