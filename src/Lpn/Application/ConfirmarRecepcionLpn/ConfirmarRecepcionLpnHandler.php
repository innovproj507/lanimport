<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\ConfirmarRecepcionLpn;

use Courier\Carga\Application\SincronizarEstadoCarga\SincronizarEstadoCargaCommand;
use Courier\Carga\Application\SincronizarEstadoCarga\SincronizarEstadoCargaHandler;
use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\Lpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Lpn\Domain\UbicacionRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

/** Bodega escanea la etiqueta pegada al bulto: el LPN pasa de "En recepcion" a "Disponible". */
final class ConfirmarRecepcionLpnHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly LpnRepositoryInterface $lpns,
        private readonly UbicacionRepositoryInterface $ubicaciones,
        private readonly SincronizarEstadoCargaHandler $sincronizarCarga,
    ) {
    }

    public function handle(CommandInterface $command): Lpn
    {
        assert($command instanceof ConfirmarRecepcionLpnCommand);

        $codigo = self::normalizarCodigo($command->codigoEscaneado);

        if ($codigo === '') {
            throw new ValidationException('Escanee o escriba el codigo del LPN.');
        }

        $lpn = $this->lpns->findByCodigo($codigo);

        if ($lpn === null) {
            throw new NotFoundException("No se encontro ningun LPN con el codigo {$codigo}.");
        }

        if ($command->cargaId !== null && $lpn->cargaId() !== $command->cargaId) {
            throw new ValidationException("El LPN {$codigo} no es de esta carga. Use Recepcion (escaneo general) para recibirlo.");
        }

        if ($lpn->estado() !== EstadoLpn::RECEIVING) {
            throw new ValidationException("El LPN {$codigo} ya fue recibido (estado: {$lpn->estado()->label()}).");
        }

        if ($command->ubicacionId !== null && $this->ubicaciones->findById($command->ubicacionId) === null) {
            throw new ValidationException('Seleccione una ubicacion valida.');
        }

        $lpn->confirmarRecepcion($command->ubicacionId);
        $this->lpns->save($lpn);

        // Con el ultimo bulto escaneado la carga pasa de "Ingresado" a "En bodega".
        if ($lpn->cargaId() !== null) {
            $this->sincronizarCarga->handle(new SincronizarEstadoCargaCommand($lpn->cargaId(), $command->usuarioId));
        }

        return $lpn;
    }

    /**
     * Los lectores de codigo de barras escriben como un teclado: con la distribucion en espanol
     * el guion sale como apostrofo o signo de pregunta, y a veces en minusculas.
     */
    public static function normalizarCodigo(string $codigo): string
    {
        return strtoupper(str_replace(["'", '?', '_', ' '], ['-', '-', '-', ''], trim($codigo)));
    }
}
