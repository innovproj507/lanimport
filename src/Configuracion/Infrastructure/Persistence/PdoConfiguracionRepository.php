<?php

declare(strict_types=1);

namespace Courier\Configuracion\Infrastructure\Persistence;

use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Configuracion\Domain\ConfiguracionSmtp;
use Courier\Configuracion\Domain\DatosEmpresa;
use Courier\Configuracion\Domain\PreferenciasNotificacion;
use Courier\Configuracion\Infrastructure\Security\SecretCipher;
use Courier\Shared\Infrastructure\Config;
use PDO;

/**
 * Configuracion del sistema en la tabla clave/valor `configuracion`, un
 * registro JSON por grupo ('smtp', 'empresa', 'notificaciones').
 */
final class PdoConfiguracionRepository implements ConfiguracionRepositoryInterface
{
    private const SMTP = 'smtp';
    private const EMPRESA = 'empresa';
    private const NOTIFICACIONES = 'notificaciones';

    public function __construct(
        private readonly PDO $connection,
        private readonly SecretCipher $cipher,
    ) {
    }

    public function smtp(): ConfiguracionSmtp
    {
        $datos = $this->leer(self::SMTP);

        if ($datos === null) {
            return new ConfiguracionSmtp(
                (string) Config::get('MAIL_HOST', 'localhost'),
                Config::int('MAIL_PORT', 25),
                Config::get('MAIL_USERNAME'),
                Config::get('MAIL_PASSWORD'),
                (string) Config::get('MAIL_ENCRYPTION', ''),
                (string) Config::get('MAIL_FROM_ADDRESS', 'no-reply@courier.local'),
                (string) Config::get('MAIL_FROM_NAME', 'Courier'),
            );
        }

        $password = isset($datos['password']) && $datos['password'] !== ''
            ? $this->cipher->descifrar((string) $datos['password'])
            : null;

        return new ConfiguracionSmtp(
            (string) $datos['host'],
            (int) $datos['puerto'],
            $datos['usuario'] ?? null,
            $password,
            (string) ($datos['cifrado'] ?? ''),
            (string) $datos['remitente_email'],
            (string) $datos['remitente_nombre'],
        );
    }

    public function smtpGuardadoEnSistema(): bool
    {
        return $this->leer(self::SMTP) !== null;
    }

    public function guardarSmtp(ConfiguracionSmtp $smtp, string $usuarioId): void
    {
        $this->escribir(self::SMTP, [
            'host' => $smtp->host,
            'puerto' => $smtp->puerto,
            'usuario' => $smtp->usuario,
            'password' => $smtp->password !== null && $smtp->password !== '' ? $this->cipher->cifrar($smtp->password) : '',
            'cifrado' => $smtp->cifrado,
            'remitente_email' => $smtp->remitenteEmail,
            'remitente_nombre' => $smtp->remitenteNombre,
        ], $usuarioId);
    }

    public function datosEmpresa(): DatosEmpresa
    {
        $datos = $this->leer(self::EMPRESA);

        if ($datos === null) {
            return DatosEmpresa::porDefecto();
        }

        return new DatosEmpresa(
            (string) $datos['nombre'],
            $datos['ruc'] ?? null,
            $datos['dv'] ?? null,
            $datos['clave_operaciones'] ?? null,
            $datos['direccion'] ?? null,
            $datos['telefono'] ?? null,
            $datos['email'] ?? null,
        );
    }

    public function guardarDatosEmpresa(DatosEmpresa $empresa, string $usuarioId): void
    {
        $this->escribir(self::EMPRESA, [
            'nombre' => $empresa->nombre,
            'ruc' => $empresa->ruc,
            'dv' => $empresa->dv,
            'clave_operaciones' => $empresa->claveOperaciones,
            'direccion' => $empresa->direccion,
            'telefono' => $empresa->telefono,
            'email' => $empresa->email,
        ], $usuarioId);
    }

    public function preferenciasNotificacion(): PreferenciasNotificacion
    {
        $datos = $this->leer(self::NOTIFICACIONES);

        if ($datos === null) {
            return PreferenciasNotificacion::porDefecto();
        }

        return new PreferenciasNotificacion((bool) ($datos['notificar_ingreso_carga'] ?? true));
    }

    public function guardarPreferenciasNotificacion(PreferenciasNotificacion $preferencias, string $usuarioId): void
    {
        $this->escribir(self::NOTIFICACIONES, [
            'notificar_ingreso_carga' => $preferencias->notificarIngresoCarga,
        ], $usuarioId);
    }

    /** @return array<string, mixed>|null */
    private function leer(string $clave): ?array
    {
        $stmt = $this->connection->prepare('SELECT valor FROM configuracion WHERE clave = :clave LIMIT 1');
        $stmt->execute([':clave' => $clave]);
        $valor = $stmt->fetchColumn();

        if ($valor === false) {
            return null;
        }

        $datos = json_decode((string) $valor, true);

        return is_array($datos) ? $datos : null;
    }

    /** @param array<string, mixed> $datos */
    private function escribir(string $clave, array $datos, string $usuarioId): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO configuracion (clave, valor, actualizado_por_usuario_id, updated_at)
             VALUES (:clave, :valor, :usuario, NOW())
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_por_usuario_id = VALUES(actualizado_por_usuario_id), updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            ':clave' => $clave,
            ':valor' => json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':usuario' => $usuarioId,
        ]);
    }
}
