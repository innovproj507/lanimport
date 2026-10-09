<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

use Courier\Carga\Domain\Exception\InvalidEstadoTransitionException;

enum CargaStatus: string
{
    case INGRESADO = 'ingresado';
    case EN_BODEGA = 'en_bodega';
    case EN_TRANSITO = 'en_transito';
    case EN_ADUANA = 'en_aduana';
    case LIBERADO = 'liberado';
    case LISTO_ENTREGA = 'listo_entrega';
    case ENTREGADO = 'entregado';
    case RETENIDO = 'retenido';

    /** @return array<int, self> */
    private static function transitions(self $estado): array
    {
        return match ($estado) {
            self::INGRESADO => [self::EN_BODEGA],
            self::EN_BODEGA => [self::EN_TRANSITO, self::RETENIDO],
            self::EN_TRANSITO => [self::EN_ADUANA],
            self::EN_ADUANA => [self::LIBERADO, self::RETENIDO],
            self::LIBERADO => [self::LISTO_ENTREGA],
            self::LISTO_ENTREGA => [self::ENTREGADO],
            self::RETENIDO => [self::EN_ADUANA],
            self::ENTREGADO => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, self::transitions($this), true);
    }

    public function assertTransitionTo(self $next): void
    {
        if (!$this->canTransitionTo($next)) {
            throw new InvalidEstadoTransitionException(
                "No se puede pasar de '{$this->value}' a '{$next->value}'."
            );
        }
    }

    public function label(): string
    {
        return match ($this) {
            self::INGRESADO => 'Ingresado',
            self::EN_BODEGA => 'En bodega',
            self::EN_TRANSITO => 'En transito',
            self::EN_ADUANA => 'En aduana',
            self::LIBERADO => 'Liberado',
            self::LISTO_ENTREGA => 'Listo para retiro',
            self::ENTREGADO => 'Entregado',
            self::RETENIDO => 'Retenido',
        };
    }
}
