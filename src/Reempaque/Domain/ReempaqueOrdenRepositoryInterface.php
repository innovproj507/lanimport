<?php

declare(strict_types=1);

namespace Courier\Reempaque\Domain;

interface ReempaqueOrdenRepositoryInterface
{
    public function save(ReempaqueOrden $orden): void;

    public function findById(string $id): ?ReempaqueOrden;

    /**
     * @param array{estado?: string, clienteId?: string} $filtros
     * @return array<int, ReempaqueOrden>
     */
    public function search(array $filtros): array;

    /**
     * A nombre de que cliente quedan las cajas: guarda el destino en la orden (null = el de las
     * cargas) y pasa sus cajas ya armadas a $clienteCajasId.
     */
    public function cambiarClienteCajas(string $ordenId, ?string $clienteDestinoId, string $clienteCajasId): void;

    /** Carga (nuevo ingreso) que se genero con las cajas de esta orden, o null. */
    public function cargaGenerada(string $ordenId): ?string;

    /**
     * Guarda el nuevo ingreso de la orden y enlaza cada caja con el bulto de la carga nueva
     * que la reemplaza (lpn_genealogia caja -> bulto, sin orden de reempaque).
     *
     * @param array<string, string> $cajaABulto cajaLpnId => lpnId nuevo
     */
    public function registrarIngresoGenerado(string $ordenId, string $cargaId, array $cajaABulto): void;
}
