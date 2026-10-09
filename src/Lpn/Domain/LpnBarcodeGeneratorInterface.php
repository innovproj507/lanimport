<?php

declare(strict_types=1);

namespace Courier\Lpn\Domain;

interface LpnBarcodeGeneratorInterface
{
    public function generate(
        string $codigo,
        ?string $marca = null,
        ?string $descripcion = null,
        ?string $proveedorNombre = null,
        ?int $ordenEnCarga = null,
        ?int $totalEnCarga = null,
        bool $mostrarSerie = false,
    ): string;

    public function eliminar(string $codigo): void;
}
