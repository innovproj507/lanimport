<?php

declare(strict_types=1);

namespace Courier\Notificacion\Domain;

interface EmailServiceInterface
{
    public function send(string $to, string $subject, string $htmlBody): bool;

    /** Motivo del ultimo envio fallido (mensaje del servidor SMTP), o null si el ultimo envio salio bien. */
    public function ultimoError(): ?string;
}
