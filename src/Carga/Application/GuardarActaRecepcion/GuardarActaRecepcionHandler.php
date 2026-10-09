<?php

declare(strict_types=1);

namespace Courier\Carga\Application\GuardarActaRecepcion;

use Courier\Auth\Domain\Rol;
use Courier\Carga\Domain\ActaRecepcion;
use Courier\Carga\Domain\ActaRecepcionRepositoryInterface;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\Exception\AccionNoPermitidaException;
use Courier\Carga\Domain\Exception\CargaNotFoundException;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;

/**
 * Registra un acta de recepcion (solo bodega/admin), con o sin carga ya
 * registrada, o corrige una existente (bodega/gerente/admin). El verificador
 * original se conserva; las correcciones quedan en actualizado_por.
 */
final class GuardarActaRecepcionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly ActaRecepcionRepositoryInterface $actas,
        private readonly ClienteRepositoryInterface $clientes,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof GuardarActaRecepcionCommand);

        $rol = $command->rolUsuario !== null ? Rol::tryFrom($command->rolUsuario) : null;

        $acta = $command->actaId === null ? $this->registrar($command, $rol) : $this->corregir($command, $rol);
        $this->actas->save($acta);

        return $acta->id();
    }

    private function registrar(GuardarActaRecepcionCommand $command, ?Rol $rol): ActaRecepcion
    {
        if (!ActaRecepcion::puedeRegistrar($rol)) {
            throw new AccionNoPermitidaException('Solo el personal de bodega puede llenar el acta de recepcion.');
        }

        $clienteId = $command->clienteId;

        if ($command->cargaId !== null) {
            $carga = $this->cargas->findById($command->cargaId);

            if ($carga === null) {
                throw new CargaNotFoundException('Carga no encontrada.');
            }

            if ($this->actas->findByCargaId($carga->id()) !== null) {
                throw new ValidationException('Esta carga ya tiene acta de recepcion.');
            }

            $clienteId = $carga->clienteId();
        }

        $this->assertClienteExiste($clienteId);

        return ActaRecepcion::registrar(
            Uuid::generate(),
            $command->cargaId,
            $clienteId,
            $command->marca,
            $command->empresaTransporte,
            $command->choferNombre,
            $command->placa,
            $command->cantidadBultos,
            $command->cantidadRollos,
            $command->totalRecibido,
            $command->tiposMercancia,
            $command->descripcionMercancia,
            $command->entregadoPor,
            $command->entregadoPorCedula,
            $command->usuarioId,
        );
    }

    private function corregir(GuardarActaRecepcionCommand $command, ?Rol $rol): ActaRecepcion
    {
        $acta = $this->actas->findById((string) $command->actaId);

        if ($acta === null) {
            throw new NotFoundException('Acta de recepcion no encontrada.');
        }

        if (!ActaRecepcion::puedeCorregir($rol)) {
            throw new AccionNoPermitidaException('No tiene permisos para corregir el acta de recepcion.');
        }

        // Con carga asociada el cliente queda fijo (es el de la carga).
        $clienteId = $acta->cargaId() !== null ? $acta->clienteId() : $command->clienteId;
        $this->assertClienteExiste($clienteId);

        $acta->corregir(
            $clienteId,
            $command->marca,
            $command->empresaTransporte,
            $command->choferNombre,
            $command->placa,
            $command->cantidadBultos,
            $command->cantidadRollos,
            $command->totalRecibido,
            $command->tiposMercancia,
            $command->descripcionMercancia,
            $command->entregadoPor,
            $command->entregadoPorCedula,
            $command->usuarioId,
        );

        return $acta;
    }

    private function assertClienteExiste(string $clienteId): void
    {
        if ($clienteId === '' || $this->clientes->findById($clienteId) === null) {
            throw new ValidationException('Seleccione el cliente de la lista.');
        }
    }
}
