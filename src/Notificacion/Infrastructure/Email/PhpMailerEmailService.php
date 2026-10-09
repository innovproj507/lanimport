<?php

declare(strict_types=1);

namespace Courier\Notificacion\Infrastructure\Email;

use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Configuracion\Domain\ProbadorSmtpInterface;
use Courier\Notificacion\Domain\EmailServiceInterface;
use Courier\Shared\Domain\Exception\DomainException;
use Monolog\Logger;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envia correos con la configuracion SMTP de Configuracion > Correo
 * (o la de MAIL_* en .env mientras no se haya guardado desde el sistema).
 */
final class PhpMailerEmailService implements EmailServiceInterface, ProbadorSmtpInterface
{
    public function __construct(
        private readonly Logger $logger,
        private readonly ConfiguracionRepositoryInterface $configuracion,
    ) {
    }

    private ?string $ultimoError = null;

    public function ultimoError(): ?string
    {
        return $this->ultimoError;
    }

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        $this->ultimoError = null;

        try {
            $this->enviar($to, $subject, $htmlBody);

            return true;
        } catch (PHPMailerException $e) {
            $this->ultimoError = $e->getMessage();
            $this->logger->error('Fallo al enviar email de notificacion', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function enviarPrueba(string $destinatario): void
    {
        try {
            $this->enviar(
                $destinatario,
                'Correo de prueba - Courier',
                '<p>Este es un correo de prueba enviado desde Configuracion &gt; Correo.</p>'
                . '<p>Si lo recibio, la configuracion SMTP funciona correctamente.</p>',
            );
        } catch (PHPMailerException $e) {
            throw new DomainException('No se pudo enviar el correo de prueba: ' . $e->getMessage(), 0, $e);
        }
    }

    /** @throws PHPMailerException */
    private function enviar(string $to, string $subject, string $htmlBody): void
    {
        $smtp = $this->configuracion->smtp();
        $mailer = new PHPMailer(true);
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;

        $mailer->isSMTP();
        $mailer->Host = $smtp->host;
        $mailer->Port = $smtp->puerto;
        $mailer->Timeout = 15;

        if ($smtp->usaAutenticacion()) {
            $mailer->SMTPAuth = true;
            $mailer->Username = (string) $smtp->usuario;
            $mailer->Password = (string) $smtp->password;
        }

        if ($smtp->cifrado !== '') {
            $mailer->SMTPSecure = $smtp->cifrado;
        }

        $mailer->setFrom($smtp->remitenteEmail, $smtp->remitenteNombre);
        $mailer->addAddress($to);
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $htmlBody;

        $mailer->send();
    }
}
