<?php

declare(strict_types=1);

namespace Courier\Carga\Application\RegistrarCarga;

final class RegistrarCargaResult
{
    /**
     * @param string|null $correoEstado 'enviado' | 'fallido' | null (aviso desactivado o cliente sin datos)
     */
    public function __construct(
        public readonly string $cargaId,
        public readonly string $trackingNumero,
        public readonly string $etiquetaUrl,
        public readonly ?string $correoEstado = null,
        public readonly ?string $correoDestinatario = null,
        public readonly ?string $correoError = null,
    ) {
    }
}
