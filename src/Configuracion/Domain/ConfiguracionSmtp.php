<?php

declare(strict_types=1);

namespace Courier\Configuracion\Domain;

use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\EmailAddress;

/**
 * Servidor de correo saliente usado para todas las notificaciones del
 * sistema (ingreso de carga, aprobacion de clientes, recuperar contrasena).
 */
final class ConfiguracionSmtp
{
    public const CIFRADOS = ['' => 'Ninguno', 'tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)'];

    public function __construct(
        public readonly string $host,
        public readonly int $puerto,
        public readonly ?string $usuario,
        public readonly ?string $password,
        public readonly string $cifrado,
        public readonly string $remitenteEmail,
        public readonly string $remitenteNombre,
    ) {
        if (trim($host) === '') {
            throw new ValidationException('Indique el servidor SMTP.');
        }

        if ($puerto < 1 || $puerto > 65535) {
            throw new ValidationException('El puerto SMTP debe estar entre 1 y 65535.');
        }

        if (!array_key_exists($cifrado, self::CIFRADOS)) {
            throw new ValidationException('Tipo de cifrado SMTP invalido.');
        }

        new EmailAddress($remitenteEmail);

        if (trim($remitenteNombre) === '') {
            throw new ValidationException('Indique el nombre del remitente.');
        }
    }

    public function usaAutenticacion(): bool
    {
        return $this->usuario !== null && $this->usuario !== '';
    }
}
