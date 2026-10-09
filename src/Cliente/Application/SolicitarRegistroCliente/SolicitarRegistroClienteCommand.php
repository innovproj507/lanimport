<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\SolicitarRegistroCliente;

use Courier\Shared\Application\CommandInterface;

final class SolicitarRegistroClienteCommand implements CommandInterface
{
    /** @param array<int, array{nombre_archivo: string, ruta_archivo: string, tipo: string}> $archivos */
    public function __construct(
        public readonly string $clienteId,
        public readonly string $nombre,
        public readonly ?string $apellido,
        public readonly string $email,
        public readonly ?string $telefono,
        public readonly ?string $direccion,
        public readonly ?string $empresa,
        public readonly ?string $pais,
        public readonly array $archivos,
    ) {
    }
}
