<?php

declare(strict_types=1);

namespace Courier\Carga\Application\RegistrarCarga;

use Courier\Carga\Domain\BarcodeGeneratorInterface;
use Courier\Carga\Domain\Carga;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\CargaStatus;
use Courier\Carga\Domain\TipoServicio;
use Courier\Carga\Domain\TrackingNumeroGeneratorInterface;
use Courier\Carga\Domain\ValueObject\Aduanero;
use Courier\Carga\Domain\ValueObject\Contenedor;
use Courier\Carga\Domain\ValueObject\DetalleAereo;
use Courier\Carga\Domain\ValueObject\DetalleFreight;
use Courier\Carga\Domain\ValueObject\DetalleMaritimo;
use Courier\Carga\Domain\ValueObject\DetalleServicioInterface;
use Courier\Carga\Domain\ValueObject\LineaCarga;
use Courier\Carga\Infrastructure\Persistence\PdoEtiquetaRepository;
use Courier\Carga\Infrastructure\Persistence\PdoHistorialEstadoRepository;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Notificacion\Application\NotificarIngresoCarga\NotificarIngresoCargaHandler;
use Courier\Notificacion\Domain\Notificacion;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;

final class RegistrarCargaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly TrackingNumeroGeneratorInterface $trackingGenerator,
        private readonly BarcodeGeneratorInterface $barcodeGenerator,
        private readonly PdoHistorialEstadoRepository $historialRepository,
        private readonly PdoEtiquetaRepository $etiquetaRepository,
        private readonly ClienteRepositoryInterface $clientes,
        private readonly NotificarIngresoCargaHandler $notificarIngreso,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): RegistrarCargaResult
    {
        assert($command instanceof RegistrarCargaCommand);

        $tipoServicio = $command->tipoServicio !== null && $command->tipoServicio !== ''
            ? TipoServicio::from($command->tipoServicio)
            : null;
        $detalle = $tipoServicio !== null ? $this->construirDetalle($tipoServicio, $command->detalleServicio) : null;
        $aduanero = $command->aduaneroIdentificador !== null && $command->aduaneroIdentificador !== ''
            ? new Aduanero($command->aduaneroIdentificador, (string) $command->aduaneroNombre)
            : null;
        $trackingNumero = $this->trackingGenerator->generate();

        try {
            $fechaIngreso = new DateTimeImmutable($command->fechaIngreso);
        } catch (\Exception $e) {
            throw new ValidationException('Fecha de ingreso invalida.');
        }

        $lineas = array_map(
            fn (array $linea) => LineaCarga::fromArray($linea),
            $command->lineas,
        );

        $carga = Carga::registrar(
            $trackingNumero,
            $command->clienteId,
            $command->proveedor,
            $command->proveedorIdentificador,
            $aduanero,
            $command->numeroContenedor !== null ? new Contenedor($command->numeroContenedor) : null,
            $tipoServicio,
            $detalle,
            $command->registradoPorUsuarioId,
            $fechaIngreso,
            $lineas,
            $command->notas,
            $command->facturaProveedorNombre,
            $command->facturaProveedorRuta,
        );

        $carga->cambiarEstado(CargaStatus::EN_BODEGA, $command->registradoPorUsuarioId, 'Ingreso fisico a bodega');

        $etiquetaUrl = $this->unitOfWork->run(function () use ($carga) {
            $this->cargas->save($carga);

            foreach ($carga->historial() as $entry) {
                $this->historialRepository->append($carga->id(), $entry);
            }

            $etiquetaUrl = $this->barcodeGenerator->generate($carga->trackingNumero());
            $this->etiquetaRepository->save($carga->id(), $etiquetaUrl);

            return $etiquetaUrl;
        });

        $notificacion = $this->notificarCliente($carga, $detalle);

        return new RegistrarCargaResult(
            $carga->id(),
            (string) $carga->trackingNumero(),
            $etiquetaUrl,
            $notificacion?->estadoEnvio()->value,
            $notificacion?->destinatarioEmail(),
            $notificacion?->errorMensaje(),
        );
    }

    private function construirDetalle(TipoServicio $tipo, array $datos): DetalleServicioInterface
    {
        try {
            return match ($tipo) {
                TipoServicio::AEREO => new DetalleAereo(
                    (string) ($datos['numero_vuelo'] ?? ''),
                    new DateTimeImmutable((string) ($datos['fecha_estimada_llegada'] ?? '')),
                    (string) ($datos['aerolinea'] ?? ''),
                ),
                TipoServicio::MARITIMO => new DetalleMaritimo(
                    (string) ($datos['numero_buque'] ?? ''),
                    new DateTimeImmutable((string) ($datos['fecha_estimada_llegada'] ?? '')),
                    (string) ($datos['puerto_origen'] ?? ''),
                    (string) ($datos['puerto_destino'] ?? ''),
                ),
                TipoServicio::FREIGHT => new DetalleFreight(
                    (string) ($datos['numero_bl'] ?? ''),
                    (string) ($datos['tipo_contenedor'] ?? ''),
                    (float) ($datos['peso_bruto'] ?? 0),
                    (float) ($datos['peso_neto'] ?? 0),
                    isset($datos['fecha_estimada_llegada']) && $datos['fecha_estimada_llegada'] !== ''
                        ? new DateTimeImmutable((string) $datos['fecha_estimada_llegada'])
                        : null,
                ),
            };
        } catch (\Exception $e) {
            throw new ValidationException('Datos de detalle de servicio invalidos o incompletos: ' . $e->getMessage());
        }
    }

    private function notificarCliente(Carga $carga, ?DetalleServicioInterface $detalle): ?Notificacion
    {
        $cliente = $this->clientes->findById($carga->clienteId());

        if ($cliente === null) {
            return null;
        }

        return $this->notificarIngreso->handle(
            $carga->id(),
            (string) $cliente->email(),
            $cliente->nombreCompleto(),
            (string) $carga->trackingNumero(),
            $detalle?->fechaEstimadaLlegada(),
            $carga->fechaIngreso(),
            $carga->proveedor(),
            array_sum(array_map(static fn ($linea) => $linea->cantidad(), $carga->lineas())),
        );
    }
}
