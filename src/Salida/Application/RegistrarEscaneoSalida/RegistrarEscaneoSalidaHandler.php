<?php

declare(strict_types=1);

namespace Courier\Salida\Application\RegistrarEscaneoSalida;

use Courier\Carga\Domain\BitacoraCargaInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Domain\BodegaActivaInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Salida\Domain\Exception\OrdenSalidaNotFoundException;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;

final class RegistrarEscaneoSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly ?BitacoraCargaInterface $bitacora = null,
        private readonly ?BodegaActivaInterface $bodegaActiva = null,
        private readonly ?LpnRepositoryInterface $lpns = null,
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

        // El personal de una bodega solo carga lo que esta en su bodega.
        $fija = $this->bodegaActiva?->fija();
        if ($fija !== null && $this->lpns !== null) {
            $lpn = $this->lpns->findByCodigo(trim($command->codigoEscaneado));
            if ($lpn !== null && $lpn->bodegaId() !== $fija) {
                throw new ValidationException("El bulto {$lpn->codigo()} esta en otra bodega: lo escanea esa bodega.");
            }
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
