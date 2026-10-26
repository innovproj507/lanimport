<?php

declare(strict_types=1);

namespace Courier\Salida\Application\AjustarBultosCargaSalida;

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
 * Trafico decide que bultos de una carga van en un DO pendiente (p.ej. 20 de 143): los que deja
 * marcados quedan en el DO (conservan su escaneo), los que desmarca vuelven a bodega como
 * disponibles, y los disponibles que marca se agregan.
 */
final class AjustarBultosCargaSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return array{quedan: int, quitados: int, agregados: int} */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof AjustarBultosCargaSalidaCommand);

        $orden = $this->ordenes->findById($command->ordenId) ?? throw new NotFoundException('Orden de salida no encontrada.');
        $elegidos = array_values(array_unique(array_map('strval', $command->lpnIds)));

        if ($elegidos === []) {
            throw new ValidationException('Deje marcado al menos un bulto. Para sacar toda la carga use "Devolver a bodega".');
        }

        // Bultos de esta carga que ya estan en el DO.
        $actuales = [];
        foreach ($orden->lineas() as $linea) {
            $lpn = $this->lpns->findById($linea->lpnId());

            if ($lpn !== null && $lpn->cargaId() === $command->cargaId) {
                $actuales[] = $linea->lpnId();
            }
        }

        $quitar = array_values(array_diff($actuales, $elegidos));
        $agregarIds = array_values(array_diff($elegidos, $actuales));

        $agregar = [];
        foreach ($agregarIds as $lpnId) {
            $lpn = $this->lpns->findById($lpnId) ?? throw new ValidationException('Uno de los bultos elegidos ya no existe. Recargue la pagina.');

            if ($lpn->cargaId() !== $command->cargaId) {
                throw new ValidationException("El bulto {$lpn->codigo()} no es de esta carga.");
            }

            if ($lpn->estado() !== EstadoLpn::AVAILABLE) {
                throw new ValidationException("El bulto {$lpn->codigo()} ya no esta disponible (sin escanear o en otra salida). Recargue la pagina.");
            }

            $agregar[] = $lpn;
        }

        $sacadas = $quitar !== [] ? $orden->sacarLineas($quitar) : [];

        if ($agregar !== []) {
            $orden->agregarLineas(
                array_map(static fn ($lpn) => new SalidaLinea($lpn->id(), (string) $lpn->codigo()), $agregar),
                array_map(static fn ($lpn) => (string) $lpn->clienteId(), $agregar),
            );
        }

        $this->unitOfWork->run(function () use ($orden, $sacadas, $agregar): void {
            $this->ordenes->save($orden);

            foreach ($sacadas as $linea) {
                $lpn = $this->lpns->findById($linea->lpnId());

                if ($lpn !== null) {
                    $lpn->liberarReserva();
                    $this->lpns->save($lpn);
                }
            }

            foreach ($agregar as $lpn) {
                $lpn->reservarParaSalida();
                $this->lpns->save($lpn);
            }
        });

        return ['quedan' => count($elegidos), 'quitados' => count($sacadas), 'agregados' => count($agregar)];
    }
}
