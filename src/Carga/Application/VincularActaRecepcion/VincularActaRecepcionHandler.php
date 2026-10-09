<?php

declare(strict_types=1);

namespace Courier\Carga\Application\VincularActaRecepcion;

use Courier\Carga\Domain\ActaRecepcion;
use Courier\Carga\Domain\ActaRecepcionRepositoryInterface;
use Courier\Carga\Domain\Carga;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;

/**
 * Asocia un acta registrada por bodega (sin carga) con la carga que
 * operaciones registro para esa mercancia. Mismo cliente obligatorio.
 */
final class VincularActaRecepcionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly ActaRecepcionRepositoryInterface $actas,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof VincularActaRecepcionCommand);

        [$acta, $carga] = $this->validar($command->actaId, $command->cargaId, null);

        $acta->vincularCarga($carga->id(), $carga->clienteId());
        $this->actas->save($acta);

        return null;
    }

    /**
     * Comprueba que el acta pueda vincularse, sin guardar nada. Con $clienteId
     * valida contra el cliente de una carga que todavia no existe (Ingreso de Carga).
     *
     * @return array{0: ActaRecepcion, 1: ?Carga}
     */
    public function validar(string $actaId, ?string $cargaId, ?string $clienteId): array
    {
        $acta = $this->actas->findById($actaId);

        if ($acta === null) {
            throw new ValidationException('El acta de recepcion seleccionada no existe.');
        }

        if ($acta->cargaId() !== null) {
            throw new ValidationException("El acta {$acta->codigo()} ya esta vinculada a otra carga.");
        }

        $carga = null;

        if ($cargaId !== null) {
            $carga = $this->cargas->findById($cargaId);

            if ($carga === null) {
                throw new ValidationException('Carga no encontrada.');
            }

            if ($this->actas->findByCargaId($carga->id()) !== null) {
                throw new ValidationException('Esa carga ya tiene acta de recepcion.');
            }

            $clienteId = $carga->clienteId();
        }

        if ($clienteId !== null && $clienteId !== $acta->clienteId()) {
            throw new ValidationException("El acta {$acta->codigo()} es de otro cliente; seleccione el mismo cliente.");
        }

        return [$acta, $carga];
    }
}
