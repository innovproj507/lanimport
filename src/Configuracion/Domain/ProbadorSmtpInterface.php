<?php

declare(strict_types=1);

namespace Courier\Configuracion\Domain;

interface ProbadorSmtpInterface
{
    /**
     * Envia un correo de prueba con la configuracion SMTP guardada.
     *
     * @throws \Courier\Shared\Domain\Exception\DomainException con el error del servidor si falla
     */
    public function enviarPrueba(string $destinatario): void;
}
