<?php

declare(strict_types=1);

namespace Courier\Configuracion\Application\GuardarConfiguracionSmtp;

use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Configuracion\Domain\ConfiguracionSmtp;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;

final class GuardarConfiguracionSmtpHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ConfiguracionRepositoryInterface $configuracion)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof GuardarConfiguracionSmtpCommand);

        $password = match (true) {
            $command->borrarPassword => null,
            $command->passwordNueva !== null => $command->passwordNueva,
            default => $this->configuracion->smtp()->password,
        };

        $smtp = new ConfiguracionSmtp(
            trim($command->host),
            $command->puerto,
            $command->usuario !== null && trim($command->usuario) !== '' ? trim($command->usuario) : null,
            $password,
            $command->cifrado,
            trim($command->remitenteEmail),
            trim($command->remitenteNombre),
        );

        $this->configuracion->guardarSmtp($smtp, $command->usuarioId);

        return null;
    }
}
