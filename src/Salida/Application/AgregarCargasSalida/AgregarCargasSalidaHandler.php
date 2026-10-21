<?php

declare(strict_types=1);

namespace Courier\Salida\Application\AgregarCargasSalida;

use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Salida\Domain\SalidaLinea;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Agrega cargas a una salida (DO) pendiente: mismo contenedor, mas bultos. Los LPNs nuevos
 * quedan reservados y bodega los escanea como los demas antes de finalizar.
 */
final class AgregarCargasSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly LpnRepositoryInterface $lpns,
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return int bultos agregados */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof AgregarCargasSalidaCommand);

        $orden = $this->ordenes->findById($command->ordenId) ?? throw new NotFoundException('Orden de salida no encontrada.');

        $lpns = [];
        foreach (array_unique($command->lpnIds) as $lpnId) {
            $lpn = $this->lpns->findById($lpnId) ?? throw new ValidationException("No se encontro el LPN con id {$lpnId}.");

            if ($lpn->estado() !== EstadoLpn::AVAILABLE) {
                throw new ValidationException("El LPN {$lpn->codigo()} ya no esta disponible (otra salida lo tomo o cambio de estado). Recargue la pagina.");
            }

            $lpns[] = $lpn;
        }

        $orden->agregarLineas(
            array_map(static fn ($lpn) => new SalidaLinea($lpn->id(), (string) $lpn->codigo()), $lpns),
            array_map(static fn ($lpn) => (string) $lpn->clienteId(), $lpns),
        );

        $this->unitOfWork->run(function () use ($orden, $lpns): void {
            $this->ordenes->save($orden);

            foreach ($lpns as $lpn) {
                $lpn->reservarParaSalida();
                $this->lpns->save($lpn);
            }
        });

        return count($lpns);
    }
}
