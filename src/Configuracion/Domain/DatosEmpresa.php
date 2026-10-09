<?php

declare(strict_types=1);

namespace Courier\Configuracion\Domain;

use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\EmailAddress;

/**
 * Datos de la empresa que aparecen en documentos impresos (acta de
 * recepcion, etc.).
 */
final class DatosEmpresa
{
    public function __construct(
        public readonly string $nombre,
        public readonly ?string $ruc,
        public readonly ?string $dv,
        public readonly ?string $claveOperaciones,
        public readonly ?string $direccion,
        public readonly ?string $telefono,
        public readonly ?string $email,
    ) {
        if (trim($nombre) === '') {
            throw new ValidationException('Indique el nombre de la empresa.');
        }

        if ($email !== null && $email !== '') {
            new EmailAddress($email);
        }
    }

    public static function porDefecto(): self
    {
        return new self(
            'LAN IMPORT - EXPORT S.A.',
            '1910558-1-724406',
            '49',
            '8115',
            'CALLE 4TA AV. 6TA A LADO DE GRUPO LOGISTICO FRANCE FIELD',
            '4398573-4398358',
            null,
        );
    }
}
