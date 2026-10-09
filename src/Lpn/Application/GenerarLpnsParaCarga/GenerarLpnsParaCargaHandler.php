<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\GenerarLpnsParaCarga;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\Exception\CargaNotFoundException;
use Courier\Carga\Infrastructure\Persistence\PdoEtiquetaRepository;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Lpn\Domain\EtiquetaLpnConfigRepositoryInterface;
use Courier\Lpn\Domain\Lpn;
use Courier\Lpn\Domain\LpnBarcodeGeneratorInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Pais\Domain\PaisRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

final class GenerarLpnsParaCargaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly LpnRepositoryInterface $lpns,
        private readonly LpnBarcodeGeneratorInterface $barcodeGenerator,
        private readonly PdoEtiquetaRepository $etiquetas,
        private readonly ClienteRepositoryInterface $clientes,
        private readonly PaisRepositoryInterface $paises,
        private readonly EtiquetaLpnConfigRepositoryInterface $etiquetaConfig,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return array<int, array{lpnId: string, codigo: string, etiquetaUrl: string}> */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof GenerarLpnsParaCargaCommand);

        $carga = $this->cargas->findById($command->cargaId);

        if ($carga === null) {
            throw new CargaNotFoundException("No se encontro la carga con id {$command->cargaId}.");
        }

        if ($this->lpns->existsLpnsParaCarga($carga->id())) {
            throw new ValidationException('Ya se generaron LPNs para esta carga.');
        }

        $cantidadTotal = (int) array_sum(array_map(
            fn ($linea) => $linea->cantidad(),
            $carga->lineas(),
        ));

        if ($cantidadTotal <= 0) {
            throw new ValidationException('La carga no tiene lineas con cantidad para generar LPNs.');
        }

        $cliente = $this->clientes->findById($carga->clienteId());
        $clientePais = $cliente?->pais() !== null ? $this->paises->findByNombre($cliente->pais()) : null;

        $etiquetaConfig = $this->etiquetaConfig->obtener();
        $mostrarSerie = $etiquetaConfig->mostrarSerie() && ($clientePais?->mostrarSerieEtiqueta() ?? false);
        $proveedorNombreEtiqueta = $etiquetaConfig->mostrarProveedor() ? $carga->proveedor() : null;

        $sufijoTracking = substr((string) $carga->trackingNumero(), -6);
        $anchoConsecutivo = strlen((string) $cantidadTotal);

        $resultado = $this->unitOfWork->run(function () use ($carga, $cantidadTotal, $proveedorNombreEtiqueta, $mostrarSerie, $etiquetaConfig, $sufijoTracking, $anchoConsecutivo) {
            $generados = [];
            $ordenEnCarga = 0;

            foreach ($carga->lineas() as $linea) {
                for ($i = 0; $i < (int) $linea->cantidad(); $i++) {
                    $ordenEnCarga++;

                    $codigo = 'LPN-' . $sufijoTracking . '-' . str_pad((string) $ordenEnCarga, $anchoConsecutivo, '0', STR_PAD_LEFT);

                    $lpn = Lpn::generar(
                        Uuid::generate(),
                        $carga->id(),
                        $carga->clienteId(),
                        $codigo,
                        $linea->marca(),
                        $linea->descripcion(),
                        $ordenEnCarga,
                    );
                    $this->lpns->save($lpn);

                    $etiquetaUrl = $this->barcodeGenerator->generate(
                        $codigo,
                        $etiquetaConfig->mostrarMarca() ? $linea->marca() : null,
                        $etiquetaConfig->mostrarDescripcion() ? $linea->descripcion() : null,
                        $proveedorNombreEtiqueta,
                        $ordenEnCarga,
                        $cantidadTotal,
                        $mostrarSerie,
                    );
                    $this->etiquetas->saveParaLpn($lpn->id(), $etiquetaUrl);

                    $generados[] = ['lpnId' => $lpn->id(), 'codigo' => $codigo, 'etiquetaUrl' => $etiquetaUrl];
                }
            }

            return $generados;
        });

        return $resultado;
    }
}
