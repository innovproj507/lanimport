<?php

declare(strict_types=1);

namespace Courier\Notificacion\Application\PrepararAvisoWhatsapp;

use Courier\Shared\Application\CommandInterface;

final class PrepararAvisoWhatsappCommand implements CommandInterface
{
    public const INGRESO = 'ingreso';
    public const DESPACHO = 'despacho';

    /**
     * @param string $tipo self::INGRESO o self::DESPACHO
     * @param array<string, string|null> $datos etiqueta => valor que van en el mensaje (los vacios se omiten)
     */
    public function __construct(
        public readonly string $cargaId,
        public readonly string $tipo,
        public readonly ?string $telefono,
        public readonly ?string $pais,
        public readonly string $clienteNombre,
        public readonly string $trackingNumero,
        public readonly array $datos,
    ) {
    }
}
