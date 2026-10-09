<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\AprobarCliente;

final class AprobarClienteResult
{
    public function __construct(
        public readonly string $clienteId,
        public readonly string $usuarioId,
        public readonly bool $emailEnviado,
    ) {
    }
}
