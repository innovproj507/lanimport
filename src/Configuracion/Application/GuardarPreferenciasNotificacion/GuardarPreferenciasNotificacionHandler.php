<?php

declare(strict_types=1);

namespace Courier\Configuracion\Application\GuardarPreferenciasNotificacion;

use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Configuracion\Domain\PreferenciasNotificacion;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;

final class GuardarPreferenciasNotificacionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ConfiguracionRepositoryInterface $configuracion)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof GuardarPreferenciasNotificacionCommand);

        $this->configuracion->guardarPreferenciasNotificacion(
            new PreferenciasNotificacion($command->notificarIngresoCarga),
            $command->usuarioId,
        );

        return null;
    }
}
