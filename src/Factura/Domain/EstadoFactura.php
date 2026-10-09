<?php

declare(strict_types=1);

namespace Courier\Factura\Domain;

use Courier\Shared\Domain\Exception\ValidationException;

enum EstadoFactura: string
{
    case PENDIENTE = 'pendiente';
    case PAGADA = 'pagada';
    case ANULADA = 'anulada';

    /** @return array<int, self> */
    private static function transitions(self $estado): array
    {
        return match ($estado) {
            self::PENDIENTE => [self::PAGADA, self::ANULADA],
            self::PAGADA => [],
            self::ANULADA => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, self::transitions($this), true);
    }

    public function assertTransitionTo(self $next): void
    {
        if (!$this->canTransitionTo($next)) {
            throw new ValidationException(
                "No se puede pasar de '{$this->value}' a '{$next->value}'."
            );
        }
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::PAGADA => 'Pagada',
            self::ANULADA => 'Anulada',
        };
    }
}
