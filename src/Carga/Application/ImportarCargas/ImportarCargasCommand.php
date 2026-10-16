<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ImportarCargas;

use Courier\Shared\Application\CommandInterface;

final class ImportarCargasCommand implements CommandInterface
{
    /**
     * @param array<int, array<string, mixed>> $cargas cargas del plan de PrepararImportacionCargas (sin errores)
     * @param bool $notificarClientes true = enviar el correo de aviso a cada cliente
     */
    public function __construct(
        public readonly array $cargas,
        public readonly bool $notificarClientes,
        public readonly string $nombreArchivo,
        public readonly string $usuarioId,
    ) {
    }
}
