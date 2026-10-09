<?php

declare(strict_types=1);

namespace Courier\Configuracion\Domain;

interface ConfiguracionRepositoryInterface
{
    /** Configuracion guardada, o la del .env (MAIL_*) si nunca se guardo desde el sistema. */
    public function smtp(): ConfiguracionSmtp;

    public function smtpGuardadoEnSistema(): bool;

    public function guardarSmtp(ConfiguracionSmtp $smtp, string $usuarioId): void;

    public function datosEmpresa(): DatosEmpresa;

    public function guardarDatosEmpresa(DatosEmpresa $empresa, string $usuarioId): void;

    public function preferenciasNotificacion(): PreferenciasNotificacion;

    public function guardarPreferenciasNotificacion(PreferenciasNotificacion $preferencias, string $usuarioId): void;
}
