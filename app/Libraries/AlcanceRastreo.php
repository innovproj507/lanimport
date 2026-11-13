<?php

declare(strict_types=1);

namespace App\Libraries;

use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroQuery;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Shared\Infrastructure\Container;

/**
 * Que cargas puede rastrear cada usuario: el personal interno, todas; un cliente, solo las suyas;
 * un aduanero, solo las que tiene asignadas. Se usa en la web (/tracking) y en la API.
 */
final class AlcanceRastreo
{
    /** Valor que no coincide con ninguna carga: usuario cliente/aduanero sin ficha vinculada. */
    private const NINGUNO = '-sin-vinculo-';

    public static function query(Container $container, string $numero, ?string $rol, ?string $usuarioId): ConsultarTrackingPorNumeroQuery
    {
        if ($rol === 'cliente') {
            $cliente = $usuarioId !== null ? $container->get(ClienteRepositoryInterface::class)->findByUsuarioId($usuarioId) : null;

            return new ConsultarTrackingPorNumeroQuery($numero, $cliente?->id() ?? self::NINGUNO);
        }

        if ($rol === 'aduanero') {
            $aduanero = $usuarioId !== null ? $container->get(AduaneroRepositoryInterface::class)->findByUsuarioId($usuarioId) : null;

            return new ConsultarTrackingPorNumeroQuery($numero, null, $aduanero?->identificador() ?? self::NINGUNO);
        }

        return new ConsultarTrackingPorNumeroQuery($numero);
    }

    /** Clientes y aduaneros tienen el rastreo limitado a lo suyo. */
    public static function limitado(?string $rol): bool
    {
        return in_array($rol, ['cliente', 'aduanero'], true);
    }
}
