<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\CerrarCajaReempaque;

use Courier\Carga\Infrastructure\Persistence\PdoEtiquetaRepository;
use Courier\Lpn\Domain\LpnBarcodeGeneratorInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Domain\Exception\ReempaqueOrdenNotFoundException;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Cierra la caja abierta y genera su etiqueta: marca, "REEMPAQUE" y cuantos bultos lleva.
 * La etiqueta se imprime desde la PC (pantalla de la orden en Reempaque).
 */
final class CerrarCajaReempaqueHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ReempaqueOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly LpnBarcodeGeneratorInterface $etiquetas,
        private readonly PdoEtiquetaRepository $etiquetaRepositorio,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return array{codigo: string, bultos: int, descartada: bool} */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof CerrarCajaReempaqueCommand);

        $orden = $this->ordenes->findById($command->reempaqueOrdenId);

        if ($orden === null) {
            throw new ReempaqueOrdenNotFoundException('Orden de reempaque no encontrada.');
        }

        if ($command->descartarVacia) {
            $lpnId = $orden->descartarCajaVacia();
            $codigo = (string) $this->lpns->findById($lpnId)?->codigo();

            $this->unitOfWork->run(function () use ($orden, $lpnId) {
                $this->ordenes->save($orden);
                $this->lpns->deleteById($lpnId);
            });

            return ['codigo' => $codigo, 'bultos' => 0, 'descartada' => true];
        }

        $lpnId = $orden->cerrarCaja();
        $caja = $this->lpns->findById($lpnId);
        $bultos = count($orden->cajas()[$lpnId]['contenido']);

        $this->unitOfWork->run(function () use ($orden, $caja, $bultos) {
            $this->ordenes->save($orden);

            if ($caja !== null) {
                $ruta = $this->etiquetas->generate(
                    (string) $caja->codigo(),
                    $caja->marca(),
                    'REEMPAQUE',
                    'CONTIENE ' . $bultos . ' BULTO' . ($bultos === 1 ? '' : 'S'),
                );
                $this->etiquetaRepositorio->saveParaLpn($caja->id(), $ruta);
            }
        });

        return ['codigo' => (string) $caja?->codigo(), 'bultos' => $bultos, 'descartada' => false];
    }
}
