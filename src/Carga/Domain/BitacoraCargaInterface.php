<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

/**
 * Bitacora de movimientos de las cargas (se muestra en Rastreo). Registrar nunca debe romper la
 * operacion que lo llama: una falla de la bitacora se ignora (y se deja en el log).
 */
interface BitacoraCargaInterface
{
    public function registrar(string $cargaId, string $tipo, string $detalle, ?string $salidaOrdenId = null): void;

    /**
     * Una fila por cada carga a la que pertenecen esos LPNs (las cajas de reempaque se abren a
     * sus bultos). En $detalle, "{n}" se reemplaza por los bultos de esa carga.
     *
     * @param array<int, string> $lpnIds
     */
    public function registrarPorLpns(array $lpnIds, string $tipo, string $detalle, ?string $salidaOrdenId = null): void;

    /**
     * Guarda una copia de la carga (datos, lineas, historial) antes de borrarla, con el motivo.
     */
    public function archivarEliminada(string $cargaId, ?string $motivo): void;
}
