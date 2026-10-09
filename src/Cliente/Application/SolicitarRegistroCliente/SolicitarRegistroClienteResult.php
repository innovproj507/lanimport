<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\SolicitarRegistroCliente;

final class SolicitarRegistroClienteResult
{
    public function __construct(
        public readonly string $clienteId,
        public readonly ?string $codigo,
    ) {
    }
}
