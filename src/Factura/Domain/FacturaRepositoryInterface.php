<?php

declare(strict_types=1);

namespace Courier\Factura\Domain;

interface FacturaRepositoryInterface
{
    public function save(Factura $factura): void;

    public function findById(string $id): ?Factura;

    /**
     * @param array{estado?: string, clienteId?: string} $filtros
     * @return array<int, Factura>
     */
    public function search(array $filtros): array;
}
