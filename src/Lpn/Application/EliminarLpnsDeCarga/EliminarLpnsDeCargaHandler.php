<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\EliminarLpnsDeCarga;

use Courier\Carga\Domain\BitacoraCargaInterface;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\Exception\CargaNotFoundException;
use Courier\Carga\Infrastructure\Persistence\PdoHistorialEstadoRepository;
use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnBarcodeGeneratorInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Borra las etiquetas (LPN) de una carga para volver a generarlas (p.ej. se ingreso mal la
 * cantidad de bultos). Se permite aunque bodega ya las haya escaneado, mientras ningun bulto haya
 * entrado en una OS/DO, reempaque o traslado. Exige un motivo, que queda en el historial; la carga
 * vuelve a "Ingresado" y hay que imprimir y escanear las etiquetas nuevas.
 */
final class EliminarLpnsDeCargaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly LpnRepositoryInterface $lpns,
        private readonly LpnBarcodeGeneratorInterface $barcodeGenerator,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?BitacoraCargaInterface $bitacora = null,
        private readonly ?PdoHistorialEstadoRepository $historial = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EliminarLpnsDeCargaCommand);

        $carga = $this->cargas->findById($command->cargaId);

        if ($carga === null) {
            throw new CargaNotFoundException("No se encontro la carga con id {$command->cargaId}.");
        }

        $motivo = trim((string) $command->motivo);

        if ($motivo === '') {
            throw new ValidationException('Escriba el motivo para borrar las etiquetas (queda en el historial de la carga).');
        }

        $lpns = $this->lpns->findByCargaId($carga->id());

        if ($lpns === []) {
            throw new ValidationException('Esta carga no tiene LPNs generados.');
        }

        $escaneados = 0;
        foreach ($lpns as $lpn) {
            if (!in_array($lpn->estado(), [EstadoLpn::RECEIVING, EstadoLpn::AVAILABLE], true)) {
                throw new ValidationException(
                    "No se pueden borrar las etiquetas: el bulto {$lpn->codigo()} esta {$lpn->estado()->label()}. "
                    . 'Primero saquelo de la OS/DO, reempaque o traslado.'
                );
            }

            $escaneados += $lpn->estado() === EstadoLpn::AVAILABLE ? 1 : 0;
        }

        $usado = $this->lpns->usoEnMovimientos($carga->id());
        if ($usado !== null) {
            throw new ValidationException("No se pueden borrar las etiquetas: {$usado}. Las etiquetas que ya se movieron no se pueden rehacer.");
        }

        $motivo = mb_substr($motivo, 0, 200);
        $texto = count($lpns) . ' etiqueta(s) (LPN) eliminadas' . ($escaneados > 0 ? " ({$escaneados} ya escaneadas en bodega)" : '') . ". Motivo: {$motivo}";
        $this->bitacora?->registrar($carga->id(), 'etiquetas_eliminadas', $texto);

        // Si ya estaba "En bodega", vuelve a "Ingresado": hay que escanear las etiquetas nuevas.
        $carga->reiniciarRecepcion($command->usuarioId, "Etiquetas eliminadas para rehacerlas. Motivo: {$motivo}");

        $this->unitOfWork->run(function () use ($carga, $lpns) {
            $this->lpns->deleteByCargaId($carga->id());
            $this->cargas->save($carga);

            if ($this->historial !== null) {
                $ultimo = $carga->historial()[array_key_last($carga->historial())] ?? null;
                if ($ultimo !== null) {
                    $this->historial->append($carga->id(), $ultimo);
                }
            }

            foreach ($lpns as $lpn) {
                if ($lpn->codigo() !== null) {
                    $this->barcodeGenerator->eliminar($lpn->codigo());
                }
            }
        });

        return null;
    }
}
