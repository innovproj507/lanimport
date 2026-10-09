<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

enum TipoServicio: string
{
    case AEREO = 'aereo';
    case MARITIMO = 'maritimo';
    case FREIGHT = 'freight';
}
