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

    /** Ruta publica (relativa a public/) de la imagen de la etiqueta; la misma que devuelve generate(). */
    public function ruta(string $codigo): string;

    public function existe(string $codigo): bool;
}
