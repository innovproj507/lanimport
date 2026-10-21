<?php

declare(strict_types=1);

namespace Courier\Evidencia\Domain;

interface FotoEvidenciaRepositoryInterface
{
    public function save(FotoEvidencia $foto): void;

    public function findById(string $id): ?FotoEvidencia;

    public function delete(string $id): void;

    /**
     * Fotos de recepcion de una carga, o de una salida (todas sus cargas, o solo $cargaId).
     * Las mas recientes primero.
     *
     * @return array<int, array{id: string, ruta: string, usuario: ?string, fecha: string, cargaId: ?string}>
     */
    public function listar(TipoEvidencia $tipo, string $referenciaId, ?string $cargaId = null): array;

    /**
     * Fotos de salida de una carga, en todas las salidas en que subio.
     *
     * @return array<int, array{id: string, ruta: string, usuario: ?string, fecha: string, cargaId: ?string, salidaOrdenId: string, etiquetaDo: string}>
     */
    public function listarSalidasDeCarga(string $cargaId): array;

    /**
     * Cuantas fotos de recepcion tiene cada carga.
     *
     * @param array<int, string> $cargaIds
     * @return array<string, int>
     */
    public function contarRecepcionPorCargas(array $cargaIds): array;

    /**
     * Cuantas fotos tiene cada carga en una salida.
     *
     * @return array<string, int>
     */
    public function contarSalidaPorCarga(string $salidaOrdenId): array;
}
