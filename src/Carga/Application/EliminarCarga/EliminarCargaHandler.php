<?php

declare(strict_types=1);

namespace Courier\Carga\Application\EliminarCarga;

use Courier\Carga\Domain\BarcodeGeneratorInterface;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\ConteoBultosCargaInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;
use Courier\Carga\Domain\BitacoraCargaInterface;

/**
 * Borra una carga ingresada por error. Solo si todavia no tiene etiquetas (LPN): con LPNs ya
 * hay bultos rotulados (y quiza escaneados o en una salida), y se borrarian sin aviso.
 * Sus lineas, detalle, historial y etiqueta de tracking se van con ella, pero antes se archiva
 * una copia en cargas_eliminadas (con el motivo y quien la borro) para Rastreo.
 */
final class EliminarCargaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly ConteoBultosCargaInterface $conteo,
        private readonly BarcodeGeneratorInterface $barcode,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof EliminarCargaCommand);

        $carga = $this->cargas->findById($command->cargaId);

        if ($carga === null) {
            throw new NotFoundException('Carga no encontrada.');
        }

        $tracking = $carga->trackingNumero();
        $lpns = $this->conteo->contar($carga->id())['total'];

        if ($lpns > 0) {
            throw new ValidationException(
                "No se puede borrar la carga {$tracking}: ya tiene {$lpns} " . ($lpns === 1 ? 'etiqueta (LPN) generada' : 'etiquetas (LPN) generadas')
                . '. Solo se puede borrar una carga sin etiquetas.'
            );
        }

        // No se pierde: queda archivada (con el motivo) y Rastreo explica que se elimino.
        $this->unitOfWork->run(function () use ($carga, $command): void {
            $this->bitacora?->archivarEliminada($carga->id(), $command->motivo);
            $this->cargas->eliminar($carga->id());
        });
        $this->barcode->eliminar($tracking);

        return (string) $tracking;
    }
}
