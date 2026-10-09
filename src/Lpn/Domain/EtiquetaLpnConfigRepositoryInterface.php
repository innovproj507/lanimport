<?php

declare(strict_types=1);

namespace Courier\Lpn\Domain;

interface EtiquetaLpnConfigRepositoryInterface
{
    public function obtener(): EtiquetaLpnConfig;

    public function guardar(EtiquetaLpnConfig $config): void;
}
