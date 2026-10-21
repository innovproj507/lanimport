<?php

declare(strict_types=1);

namespace Courier\Carga\Application\BuscarCargas;

final class BuscarCargasResult
{
    /**
     * @param array<int, \Courier\Carga\Domain\Carga> $items
     * @param array{bultos: float, peso: float, pies3: float, valor: float} $resumen totales de todas las cargas filtradas
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $resumen = ['bultos' => 0.0, 'peso' => 0.0, 'pies3' => 0.0, 'valor' => 0.0],
    ) {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
