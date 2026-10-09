<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ConsultarTrackingPorNumero;

use Courier\Carga\Domain\Carga;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\Exception\CargaNotFoundException;
use Courier\Carga\Domain\ValueObject\TrackingNumero;
use Courier\Carga\Infrastructure\Persistence\PdoHistorialEstadoRepository;
use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ConsultarTrackingPorNumeroHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly PdoHistorialEstadoRepository $historialRepository,
        private readonly LpnRepositoryInterface $lpns,
    ) {
    }

    public function handle(QueryInterface $query): TrackingView
    {
        assert($query instanceof ConsultarTrackingPorNumeroQuery);

        $carga = $this->cargas->findByTrackingNumero(new TrackingNumero($query->trackingNumero));

        if ($carga === null) {
            throw new CargaNotFoundException("No se encontro una carga con tracking {$query->trackingNumero}");
        }

        return self::toView($carga, $this->historialRepository, $this->lpns);
    }

    public static function toView(Carga $carga, PdoHistorialEstadoRepository $historialRepository, LpnRepositoryInterface $lpns): TrackingView
    {
        $historial = [];

        foreach ($historialRepository->findByCargaId($carga->id()) as $entry) {
            $historial[] = [
                'estado' => $entry->estado()->value,
                'estadoLabel' => $entry->estado()->label(),
                'fecha' => $entry->fecha()->format('Y-m-d H:i:s'),
                'comentario' => $entry->comentario(),
            ];
        }

        $lineas = array_map(
            fn ($linea) => [
                'descripcion' => $linea->descripcion(),
                'unidadMedida' => $linea->unidadMedida(),
                'cantidad' => $linea->cantidad(),
                'ancho' => $linea->ancho(),
                'alto' => $linea->alto(),
                'largo' => $linea->largo(),
                'cubicajePies' => $linea->cubicajePies(),
                'peso' => $linea->peso(),
                'valor' => $linea->valor(),
                'marca' => $linea->marca(),
            ],
            $carga->lineas(),
        );

        $conteoPorEstado = [];

        foreach ($lpns->findByCargaId($carga->id()) as $lpn) {
            $conteoPorEstado[$lpn->estado()->value] = ($conteoPorEstado[$lpn->estado()->value] ?? 0) + 1;
        }

        $bultosPorEstado = [];

        foreach ($conteoPorEstado as $estadoValor => $cantidad) {
            $bultosPorEstado[] = [
                'estado' => $estadoValor,
                'estadoLabel' => EstadoLpn::from($estadoValor)->label(),
                'cantidad' => $cantidad,
            ];
        }

        return new TrackingView(
            $carga->id(),
            (string) $carga->trackingNumero(),
            $carga->estado()->value,
            $carga->estado()->label(),
            $carga->tipoServicio()?->value,
            $carga->fechaIngreso()->format('d/m/Y H:i'),
            $carga->detalle()?->fechaEstimadaLlegada()?->format('Y-m-d'),
            $carga->numeroContenedor() !== null ? (string) $carga->numeroContenedor() : null,
            $historial,
            $lineas,
            $carga->notas(),
            $carga->facturaProveedorNombre(),
            $carga->facturaProveedorRuta(),
            $bultosPorEstado,
            $carga->proveedor(),
        );
    }
}
