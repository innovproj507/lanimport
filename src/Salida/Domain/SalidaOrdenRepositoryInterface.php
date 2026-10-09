<?php

declare(strict_types=1);

namespace Courier\Salida\Domain;

interface SalidaOrdenRepositoryInterface
{
    public function save(SalidaOrden $orden): void;

    public function findById(string $id): ?SalidaOrden;

    /**
     * @param array{estado?: string, clienteId?: string} $filtros
     * @return array<int, SalidaOrden>
     */
    public function search(array $filtros): array;
}
