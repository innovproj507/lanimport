<?php

declare(strict_types=1);

namespace Courier\Traslado\Application\MoverTraslado;

use Courier\Bodega\Domain\BodegaRepositoryInterface;
use Courier\Carga\Domain\BitacoraCargaInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\BodegaActivaInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;
use Courier\Traslado\Domain\EstadoTraslado;
use Courier\Traslado\Domain\Traslado;
use Courier\Traslado\Domain\TrasladoRepositoryInterface;

/**
 * Escanear (salida o llegada), despachar o cancelar un traslado. El personal de una bodega solo
 * hace lo que le toca: la origen carga y despacha; la destino recibe.
 */
final class MoverTrasladoHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly TrasladoRepositoryInterface $traslados,
        private readonly LpnRepositoryInterface $lpns,
        private readonly BodegaRepositoryInterface $bodegas,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?BodegaActivaInterface $bodegaActiva = null,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    /** @return array{estado: string, progreso: array{salida: int, llegada: int, total: int}, mensaje: ?string} */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof MoverTrasladoCommand);

        $t = $this->traslados->findById($command->trasladoId) ?? throw new NotFoundException('Traslado no encontrado.');
        $origen = $this->bodegas->findById($t->origenBodegaId())?->nombre() ?? 'origen';
        $destino = $this->bodegas->findById($t->destinoBodegaId())?->nombre() ?? 'destino';
        $mensaje = null;

        match ($command->accion) {
            'escanear' => $mensaje = $this->escanear($t, (string) $command->codigo, $origen, $destino),
            'despachar' => $mensaje = $this->despachar($t, $command->usuarioId, $origen, $destino),
            'cancelar' => $mensaje = $this->cancelar($t, $origen),
            default => throw new ValidationException('Accion no valida.'),
        };

        return ['estado' => $t->estado()->value, 'progreso' => $t->progreso(), 'mensaje' => $mensaje];
    }

    private function escanear(Traslado $t, string $codigo, string $origen, string $destino): ?string
    {
        if (trim($codigo) === '') {
            throw new ValidationException('Escanee o escriba el codigo del bulto.');
        }

        if ($t->estado() === EstadoTraslado::PENDIENTE) {
            $this->exigirBodega($t->origenBodegaId(), "Este traslado lo carga {$origen}.");
            $t->escanearSalida($codigo);
            $this->traslados->save($t);

            return null;
        }

        $this->exigirBodega($t->destinoBodegaId(), "Este traslado lo recibe {$destino}.");
        $lpnId = $t->escanearLlegada($codigo);

        if ($lpnId === null) {
            return 'Ese bulto ya se habia recibido.';
        }

        $this->unitOfWork->run(function () use ($t, $lpnId): void {
            $lpn = $this->lpns->findById($lpnId);
            $lpn?->recibirDeTraslado($t->destinoBodegaId());

            if ($lpn !== null) {
                $this->lpns->save($lpn);
            }

            $this->traslados->save($t);
        });

        $this->bitacora?->registrarPorLpns([$lpnId], 'traslado_llegada', "Llego a {$destino} ({$t->etiqueta()})");

        return $t->estado() === EstadoTraslado::RECIBIDO ? "Traslado {$t->etiqueta()} completo: llegaron todos los bultos." : null;
    }

    private function despachar(Traslado $t, string $usuarioId, string $origen, string $destino): string
    {
        $this->exigirBodega($t->origenBodegaId(), "Este traslado lo despacha {$origen}.");
        $quitados = $t->despachar($usuarioId);

        $this->unitOfWork->run(function () use ($t, $quitados): void {
            $this->traslados->save($t);
            $this->liberar($quitados);
        });

        $this->bitacora?->registrarPorLpns($t->lpnIds(), 'traslado', "{n} bulto(s) salieron de {$origen} hacia {$destino} ({$t->etiqueta()})");
        $this->bitacora?->registrarPorLpns($quitados, 'traslado', "{n} bulto(s) no se cargaron en {$t->etiqueta()}: siguen en {$origen}");

        return "Traslado {$t->etiqueta()} despachado con " . count($t->lpnIds()) . ' bulto(s)'
            . ($quitados !== [] ? '; ' . count($quitados) . " no escaneado(s) se quedan en {$origen}." : '.');
    }

    private function cancelar(Traslado $t, string $origen): string
    {
        $lpnIds = $t->cancelar();

        $this->unitOfWork->run(function () use ($t, $lpnIds): void {
            $this->traslados->save($t);
            $this->liberar($lpnIds);
        });

        $this->bitacora?->registrarPorLpns($lpnIds, 'traslado', "Traslado {$t->etiqueta()} cancelado: {n} bulto(s) siguen disponibles en {$origen}");

        return "Traslado {$t->etiqueta()} cancelado.";
    }

    /** @param array<int, string> $lpnIds */
    private function liberar(array $lpnIds): void
    {
        foreach ($lpnIds as $lpnId) {
            $lpn = $this->lpns->findById($lpnId);

            if ($lpn !== null) {
                $lpn->cancelarTraslado();
                $this->lpns->save($lpn);
            }
        }
    }

    private function exigirBodega(string $bodegaId, string $mensaje): void
    {
        $fija = $this->bodegaActiva?->fija();

        if ($fija !== null && $fija !== $bodegaId) {
            throw new ValidationException($mensaje);
        }
    }
}
