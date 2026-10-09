<?php

declare(strict_types=1);

namespace Courier\Configuracion\Infrastructure\Security;

use Courier\Shared\Infrastructure\Config;
use RuntimeException;

/**
 * Cifra secretos guardados en la tabla `configuracion` (p.ej. la contrasena
 * SMTP) con AES-256-GCM. La llave se deriva de JWT_SECRET: si ese valor
 * cambia, los secretos guardados dejan de poder leerse y hay que volver a
 * escribirlos desde Configuracion.
 */
final class SecretCipher
{
    private const CIPHER = 'aes-256-gcm';

    private function key(): string
    {
        $secret = Config::get('JWT_SECRET');

        if ($secret === null) {
            throw new RuntimeException('JWT_SECRET no esta configurado; no se pueden cifrar secretos.');
        }

        return hash('sha256', 'configuracion|' . $secret, true);
    }

    public function cifrar(string $texto): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt($texto, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($cifrado === false) {
            throw new RuntimeException('No se pudo cifrar el secreto.');
        }

        return base64_encode($iv . $tag . $cifrado);
    }

    public function descifrar(string $payload): ?string
    {
        $raw = base64_decode($payload, true);

        if ($raw === false || strlen($raw) < 28) {
            return null;
        }

        $texto = openssl_decrypt(substr($raw, 28), self::CIPHER, $this->key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        return $texto === false ? null : $texto;
    }
}
