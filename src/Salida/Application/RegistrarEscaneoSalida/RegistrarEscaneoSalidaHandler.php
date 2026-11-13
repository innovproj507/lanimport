<?php

declare(strict_types=1);

namespace Courier\Salida\Application\RegistrarEscaneoSalida;

use Courier\Carga\Domain\BitacoraCargaInterface;
use Courier\Salida\Domain\Exception\OrdenSalidaNotFoundException;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;

final class RegistrarEscaneoSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    /** @return array{escaneadas: int, total: int} */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof RegistrarEscaneoSalidaCommand);

        $orden = $this->ordenes->findById($command->salidaOrdenId);

        if ($orden === null) {
            throw new OrdenSalidaNotFoundException("No se encontro la orden de salida con id {$command->salidaOrdenId}.");
        }

        $antes = $orden->progreso()['escaneadas'];
        $orden->registrarEscaneo($command->codigoEscaneado);
        $this->ordenes->save($orden);

        // Solo un escaneo nuevo (no el mismo bulto leido dos veces). En Rastreo se agrupan por orden y dia.
        if ($orden->progreso()['escaneadas'] > $antes) {
            foreach ($orden->lineas() as $linea) {
                if (strcasecmp($linea->codigoLpn(), trim($command->codigoEscaneado)) === 0) {
                    $this->bitacora?->registrarPorLpns([$linea->lpnId()], 'salida_escaneo', $orden->etiquetaDo(), $orden->id());
                }
            }
        }

        return $orden->progreso();
    }
}
