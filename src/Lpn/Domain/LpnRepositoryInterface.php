<?php

declare(strict_types=1);

namespace Courier\Lpn\Domain;

interface LpnRepositoryInterface
{
    public function save(Lpn $lpn): void;

    public function findById(string $id): ?Lpn;

    public function findByCodigo(string $codigo): ?Lpn;

    /** @return array<int, Lpn> */
    public function findByCargaId(string $cargaId): array;

    public function existsLpnsParaCarga(string $cargaId): bool;

    public function deleteByCargaId(string $cargaId): void;

    /** Borra un LPN que nunca existio fisicamente (caja de reempaque descartada o cancelada). */
    public function deleteById(string $id): void;

    /**
     * Cargas cuyos bultos van dentro de este LPN: la suya, o la de los bultos que se
     * reempacaron en el (recorriendo reempaques de reempaques).
     *
     * @return array<int, string> ids de carga
     */
    public function cargasContenidas(string $lpnId): array;

    /**
     * Bultos originales (con carga) que van dentro de una caja reempacada, a cualquier nivel.
     *
     * @return array<int, array{lpnId: string, codigo: string, cargaId: string, tracking: string, marca: ?string}>
     */
    public function bultosContenidos(string $lpnId): array;

    /**
     * @param array{estado?: string, ubicacionId?: string, clienteId?: string, cargaCodigo?: string} $filtros
     * @return array<int, Lpn>
     */
    public function search(array $filtros): array;

    /**
     * Avance de recepcion por carga: solo cargas con al menos un LPN todavia "En recepcion"
     * (o solo la carga indicada). Mas antiguas primero.
     *
     * @return array<int, array{cargaId: string, tracking: string, cliente: string, marca: ?string, fechaIngreso: string, total: int, recibidos: int}>
     */
    public function resumenRecepcion(?string $cargaId = null): array;

    /**
     * Cuantas etiquetas (LPN) tiene cada carga en cada estado, para las listas de cargas.
     *
     * @param array<int, string> $cargaIds
     * @return array<string, array<string, int>> cargaId => [estado => cantidad]
     */
    public function conteoPorEstado(array $cargaIds): array;
}
