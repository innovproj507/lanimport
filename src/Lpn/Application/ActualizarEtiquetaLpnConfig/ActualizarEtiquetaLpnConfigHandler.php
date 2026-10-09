<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\ActualizarEtiquetaLpnConfig;

use Courier\Lpn\Domain\EtiquetaLpnConfig;
use Courier\Lpn\Domain\EtiquetaLpnConfigRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;

final class ActualizarEtiquetaLpnConfigHandler implements CommandHandlerInterface
{
    public function __construct(private readonly EtiquetaLpnConfigRepositoryInterface $config)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarEtiquetaLpnConfigCommand);

        $config = new EtiquetaLpnConfig(
            $command->mostrarMarca,
            $command->mostrarProveedor,
            $command->mostrarSerie,
            $command->mostrarDescripcion,
        );

        $this->config->guardar($config);

        return null;
    }
}
