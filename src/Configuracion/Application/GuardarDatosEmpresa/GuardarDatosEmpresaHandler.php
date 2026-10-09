<?php

declare(strict_types=1);

namespace Courier\Configuracion\Application\GuardarDatosEmpresa;

use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Configuracion\Domain\DatosEmpresa;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;

final class GuardarDatosEmpresaHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ConfiguracionRepositoryInterface $configuracion)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof GuardarDatosEmpresaCommand);

        $empresa = new DatosEmpresa(
            trim($command->nombre),
            $command->ruc,
            $command->dv,
            $command->claveOperaciones,
            $command->direccion,
            $command->telefono,
            $command->email,
        );

        $this->configuracion->guardarDatosEmpresa($empresa, $command->usuarioId);

        return null;
    }
}
