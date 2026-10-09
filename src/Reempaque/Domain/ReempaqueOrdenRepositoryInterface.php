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
}
