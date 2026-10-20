<?php

declare(strict_types=1);

namespace Courier\Evidencia\Domain;

interface FotoEvidenciaRepositoryInterface
{
    public function save(FotoEvidencia $foto): void;

    public function findById(string $id): ?FotoEvidencia;

    public function delete(string $id): void;

    /**
     * Fotos de un momento (recepcion de una carga o salida de una orden), las mas recientes primero.
     *
     * @return array<int, array{id: string, ruta: string, usuario: ?string, fecha: string}>
     */
    public function listar(TipoEvidencia $tipo, string $referenciaId): array;

    /**
     * Cuantas fotos de recepcion tiene cada carga.
     *
     * @param array<int, string> $cargaIds
     * @return array<string, int>
     */
    public function contarRecepcionPorCargas(array $cargaIds): array;
}
