<?php

declare(strict_types=1);

namespace Courier\Carga\Application\SincronizarEstadoCarga;

use Courier\Shared\Application\CommandInterface;

final class SincronizarEstadoCargaCommand implements CommandInterface
{
    /**
     * @param string|null $detalleDespacho texto para el historial, ej. "DO.5 / 2026, contenedor TGBU105486"
     * @param array<string, string|null> $datosEmbarque etiqueta => valor para el correo de despacho
     */
    public function __construct(
        public readonly string $cargaId,
        public readonly string $usuarioId,
        public readonly ?string $detalleDespacho = null,
        public readonly array $datosEmbarque = [],
    ) {
    }
}
