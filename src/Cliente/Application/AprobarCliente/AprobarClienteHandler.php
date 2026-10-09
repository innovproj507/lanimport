<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\AprobarCliente;

use Courier\Auth\Domain\Rol;
use Courier\Auth\Domain\Usuario;
use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Auth\Infrastructure\Security\PasswordHasher;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Notificacion\Domain\EmailServiceInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;
use Monolog\Logger;

final class AprobarClienteHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ClienteRepositoryInterface $clientes,
        private readonly UsuarioRepositoryInterface $usuarios,
        private readonly PasswordHasher $hasher,
        private readonly EmailServiceInterface $emailService,
        private readonly Logger $logger,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): AprobarClienteResult
    {
        assert($command instanceof AprobarClienteCommand);

        $cliente = $this->clientes->findById($command->clienteId);

        if ($cliente === null) {
            throw new NotFoundException("No se encontro el cliente con id {$command->clienteId}.");
        }

        $passwordTemporal = bin2hex(random_bytes(6));
        $usuario = Usuario::registrar(
            Uuid::generate(),
            $cliente->nombreCompleto(),
            $cliente->email(),
            $this->hasher->hash($passwordTemporal),
            Rol::CLIENTE,
        );

        $this->unitOfWork->run(function () use ($usuario, $cliente) {
            $this->usuarios->save($usuario);
            $cliente->aprobar($usuario->id());
            $this->clientes->save($cliente);
        });

        $emailEnviado = $this->emailService->send(
            (string) $cliente->email(),
            'Tu cuenta de cliente ha sido aprobada',
            $this->cuerpoCorreo($cliente->nombreCompleto(), (string) $cliente->email(), $passwordTemporal)
        );

        if (!$emailEnviado) {
            $this->logger->error('No se pudo enviar el correo de credenciales al cliente aprobado', [
                'cliente_id' => $cliente->id(),
                'email' => (string) $cliente->email(),
            ]);
        }

        return new AprobarClienteResult($cliente->id(), $usuario->id(), $emailEnviado);
    }

    private function cuerpoCorreo(string $nombre, string $email, string $passwordTemporal): string
    {
        return "<p>Hola {$nombre},</p>"
            . '<p>Tu solicitud de registro ha sido aprobada. Ya puedes ingresar al portal de clientes con las siguientes credenciales:</p>'
            . "<p><strong>Correo:</strong> {$email}<br><strong>Contrasena temporal:</strong> {$passwordTemporal}</p>"
            . '<p>Te recomendamos cambiar tu contrasena despues de iniciar sesion.</p>';
    }
}
