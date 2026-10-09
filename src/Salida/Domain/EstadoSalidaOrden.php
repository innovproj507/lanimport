<?php

declare(strict_types=1);

namespace Courier\Salida\Domain;

use Courier\Shared\Domain\Exception\ValidationException;

enum EstadoSalidaOrden: string
{
    case PENDIENTE = 'pendiente';
    case CONFIRMADA = 'confirmada';
    case CANCELADA = 'cancelada';

    /** @return array<int, self> */
    private static function transitions(self $estado): array
    {
        return match ($estado) {
            self::PENDIENTE => [self::CONFIRMADA, self::CANCELADA],
            self::CONFIRMADA => [],
            self::CANCELADA => [],
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
            self::CONFIRMADA => 'Confirmada',
            self::CANCELADA => 'Cancelada',
        };
    }
}
