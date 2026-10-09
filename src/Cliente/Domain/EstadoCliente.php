<?php

declare(strict_types=1);

namespace Courier\Cliente\Domain;

enum EstadoCliente: string
{
    case PENDIENTE = 'pendiente';
    case APROBADO = 'aprobado';
    case RECHAZADO = 'rechazado';
}
