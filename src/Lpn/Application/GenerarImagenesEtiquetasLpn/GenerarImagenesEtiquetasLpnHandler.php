<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\GenerarImagenesEtiquetasLpn;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\Exception\CargaNotFoundException;
use Courier\Lpn\Domain\EtiquetaLpnConfigRepositoryInterface;
use Courier\Lpn\Domain\LpnBarcodeGeneratorInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;

/**
 * Dibuja las imagenes de las etiquetas LPN de una carga que todavia no existen, por tandas con
 * tiempo maximo: cada imagen tarda ~0.1 s y una carga de cientos de bultos no cabe en una sola
 * peticion (limite de 30 s de PHP y 60 s del proxy). La pantalla de recepcion llama tandas hasta
 * que no falte ninguna.
 */
final class GenerarImagenesEtiquetasLpnHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly LpnRepositoryInterface $lpns,
        private readonly LpnBarcodeGeneratorInterface $barcodeGenerator,
        private readonly EtiquetaLpnConfigRepositoryInterface $etiquetaConfig,
    ) {
    }

    public function handle(CommandInterface $command): GenerarImagenesEtiquetasLpnResult
    {
        assert($command instanceof GenerarImagenesEtiquetasLpnCommand);

        $carga = $this->cargas->findById($command->cargaId);

        if ($carga === null) {
            throw new CargaNotFoundException("No se encontro la carga con id {$command->cargaId}.");
        }

        $lpns = array_values(array_filter(
            $this->lpns->findByCargaId($carga->id()),
            static fn ($lpn) => $lpn->codigo() !== null,
        ));
        $total = count($lpns);
        $pendientes = array_values(array_filter($lpns, fn ($lpn) => !$this->barcodeGenerator->existe((string) $lpn->codigo())));

        if ($pendientes === [] || $command->segundosMax <= 0) {
            return new GenerarImagenesEtiquetasLpnResult($total, 0, count($pendientes));
        }

        // La serie (12/38) depende solo de la plantilla (Configuracion > Etiqueta LPN), no del pais del cliente.
        $config = $this->etiquetaConfig->obtener();
        $proveedor = $config->mostrarProveedor() ? $carga->proveedor() : null;
        $limite = microtime(true) + $command->segundosMax;
        $generadas = 0;

        foreach ($pendientes as $lpn) {
            if (microtime(true) >= $limite) {
                break;
            }

            $this->barcodeGenerator->generate(
                (string) $lpn->codigo(),
                $config->mostrarMarca() ? $lpn->marca() : null,
                $config->mostrarDescripcion() ? $lpn->descripcion() : null,
                $proveedor,
                $lpn->ordenEnCarga(),
                $total,
                $config->mostrarSerie(),
            );
            $generadas++;
        }

        return new GenerarImagenesEtiquetasLpnResult($total, $generadas, count($pendientes) - $generadas);
    }
}
