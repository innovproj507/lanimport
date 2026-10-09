<?php

declare(strict_types=1);

namespace Courier\Factura\Domain;

final class LineaFactura
{
    public function __construct(
        private readonly string $descripcion,
        private readonly float $cantidad,
        private readonly float $precioUnitario,
    ) {
    }

    public function subtotal(): float
    {
        return round($this->cantidad * $this->precioUnitario, 2);
    }

    public function descripcion(): string
    {
        return $this->descripcion;
    }

    public function cantidad(): float
    {
        return $this->cantidad;
    }

    public function precioUnitario(): float
    {
        return $this->precioUnitario;
    }
}
