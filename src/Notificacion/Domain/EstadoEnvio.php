<?php

declare(strict_types=1);

namespace Courier\Notificacion\Domain;

enum EstadoEnvio: string
{
    case PENDIENTE = 'pendiente';
    case ENVIADO = 'enviado';
    case FALLIDO = 'fallido';
}
