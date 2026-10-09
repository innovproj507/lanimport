<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\RegistrarCliente;

final class RegistrarClienteResult
{
    public function __construct(public readonly string $clienteId)
    {
    }
}
