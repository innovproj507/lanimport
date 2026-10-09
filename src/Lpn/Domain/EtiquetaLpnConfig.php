<?php

declare(strict_types=1);

namespace Courier\Lpn\Domain;

final class EtiquetaLpnConfig
{
    public function __construct(
        private readonly bool $mostrarMarca,
        private readonly bool $mostrarProveedor,
        private readonly bool $mostrarSerie,
        private readonly bool $mostrarDescripcion,
    ) {
    }

    public static function porDefecto(): self
    {
        return new self(true, true, true, true);
    }

    public function mostrarMarca(): bool
    {
        return $this->mostrarMarca;
    }

    public function mostrarProveedor(): bool
    {
        return $this->mostrarProveedor;
    }

    public function mostrarSerie(): bool
    {
        return $this->mostrarSerie;
    }

    public function mostrarDescripcion(): bool
    {
        return $this->mostrarDescripcion;
    }
}
