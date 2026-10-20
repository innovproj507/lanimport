<?php

declare(strict_types=1);

namespace Courier\Evidencia\Application\SubirFotoEvidencia;

use Courier\Shared\Application\CommandInterface;

final class SubirFotoEvidenciaCommand implements CommandInterface
{
    /**
     * @param string $tipo 'recepcion' (referencia = carga) o 'salida' (referencia = orden de salida)
     * @param string $archivoTemporal tmp_name de la subida
     */
    public function __construct(
        public readonly string $tipo,
        public readonly string $referenciaId,
        public readonly string $archivoTemporal,
        public readonly int $tamano,
        public readonly ?string $usuarioId,
    ) {
    }
}
