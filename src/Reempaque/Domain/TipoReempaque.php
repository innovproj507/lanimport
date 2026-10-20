<?php

declare(strict_types=1);

namespace Courier\Reempaque\Domain;

enum TipoReempaque: string
{
    case CONSOLIDATION = 'consolidation';
    case SPLIT = 'split';
    // Ya no se ofrece al crear ordenes; se conserva para leer ordenes antiguas.
    case KITTING = 'kitting';
    case REPRESENTATION_CHANGE = 'representation_change';

    public function label(): string
    {
        return match ($this) {
            self::CONSOLIDATION => 'Consolidar',
            self::SPLIT => 'Dividir',
            self::KITTING => 'Kitting',
            self::REPRESENTATION_CHANGE => 'Cambiar caja',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::CONSOLIDATION => 'Varias cajas en una caja o paleta.',
            self::SPLIT => 'Una caja o paleta en varias (por ejemplo, una paleta que no cabe).',
            self::KITTING => 'Armado de kits.',
            self::REPRESENTATION_CHANGE => 'Cambiar una caja danada por una nueva, una por una.',
        };
    }

    /** Un mismo bulto puede ir en varias cajas nuevas solo al dividir. */
    public function permiteBultoEnVariasCajas(): bool
    {
        return $this === self::SPLIT;
    }

    /** @return array<int, self> los tipos que trafico puede ordenar */
    public static function seleccionables(): array
    {
        return [self::CONSOLIDATION, self::SPLIT, self::REPRESENTATION_CHANGE];
    }
}
