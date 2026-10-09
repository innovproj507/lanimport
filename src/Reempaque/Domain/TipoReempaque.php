<?php

declare(strict_types=1);

namespace Courier\Reempaque\Domain;

enum TipoReempaque: string
{
    case CONSOLIDATION = 'consolidation';
    case SPLIT = 'split';
    case KITTING = 'kitting';
    case REPRESENTATION_CHANGE = 'representation_change';

    public function label(): string
    {
        return match ($this) {
            self::CONSOLIDATION => 'Consolidacion',
            self::SPLIT => 'Division',
            self::KITTING => 'Kitting',
            self::REPRESENTATION_CHANGE => 'Cambio de representacion',
        };
    }
}
