<?php

declare(strict_types=1);

namespace Courier\Auth\Application\SolicitarRecuperacionPassword;

use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Auth\Infrastructure\Persistence\PasswordResetTokenRepository;
use Courier\Notificacion\Domain\EmailServiceInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Infrastructure\Config;
use Monolog\Logger;

final class SolicitarRecuperacionPasswordHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarios,
        private readonly PasswordResetTokenRepository $tokens,
        private readonly EmailServiceInterface $emailService,
        private readonly Logger $logger,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SolicitarRecuperacionPasswordCommand);

        $usuario = $this->usuarios->findByEmail($command->email);

        if ($usuario === null || !$usuario->activo()) {
            return null;
        }

        $token = $this->tokens->generar($usuario->id());
        $enlace = rtrim((string) Config::get('APP_URL', ''), '/') . '/reset-password/' . $token;

        $enviado = $this->emailService->send(
            (string) $usuario->email(),
            'Recupera tu contrasena',
            $this->cuerpoCorreo($usuario->nombre(), $enlace)
        );

        if (!$enviado) {
            $this->logger->error('No se pudo enviar el correo de recuperacion de contrasena', [
                'usuario_id' => $usuario->id(),
                'email' => (string) $usuario->email(),
            ]);
        }

        return null;
    }

    private function cuerpoCorreo(string $nombre, string $enlace): string
    {
        return "<p>Hola {$nombre},</p>"
            . '<p>Recibimos una solicitud para restablecer tu contrasena. Si fuiste tu, haz clic en el siguiente enlace:</p>'
            . "<p><a href=\"{$enlace}\">{$enlace}</a></p>"
            . '<p>Este enlace expira en 60 minutos. Si no solicitaste este cambio, puedes ignorar este correo.</p>';
    }
}
