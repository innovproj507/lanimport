<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroHandler;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroQuery;
use Courier\Carga\Application\ConsultarTrackingPorNumero\TrackingView;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Config;
use DateTimeImmutable;
use Throwable;

/**
 * Rastreo para la web publica (weblan): la consulta el servidor de la web con
 * una clave compartida (header X-Api-Key = RASTREO_PUBLICO_API_KEY del .env).
 * Solo devuelve lo que el cliente final debe ver: nada de nombres, proveedor,
 * valores, notas ni comentarios internos.
 */
final class RastreoPublicoApiController extends BaseApiController
{
    public function show(string $numero): ResponseInterface
    {
        $claveEsperada = Config::get('RASTREO_PUBLICO_API_KEY');

        if ($claveEsperada === null) {
            return $this->error('El rastreo publico no esta configurado.', 503);
        }

        if (!hash_equals($claveEsperada, (string) $this->request->getHeaderLine('X-Api-Key'))) {
            return $this->error('No autorizado.', 401);
        }

        $numero = strtoupper(trim($numero));

        if (preg_match('/^[A-Z0-9-]{4,40}$/', $numero) !== 1) {
            return $this->error('Numero de tracking invalido.', 422);
        }

        try {
            $view = $this->container()->get(ConsultarTrackingPorNumeroHandler::class)
                ->handle(new ConsultarTrackingPorNumeroQuery($numero));

            return $this->success($this->publico($view));
        } catch (NotFoundException|ValidationException) {
            return $this->error('No se encontro una carga con ese numero de tracking.', 404);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** @return array<string, mixed> */
    private function publico(TrackingView $view): array
    {
        $fecha = static fn (?string $valor, string $formato): ?string => $valor !== null && $valor !== ''
            ? (new DateTimeImmutable($valor))->format($formato)
            : null;

        $bultos = array_sum(array_column($view->bultosPorEstado, 'cantidad'));

        if ($bultos === 0) {
            $bultos = (int) array_sum(array_column($view->lineas, 'cantidad'));
        }

        return [
            'tracking' => $view->trackingNumero,
            'estado' => $view->estadoActual,
            'estadoLabel' => $view->estadoActualLabel,
            'servicio' => $view->tipoServicio,
            // TrackingView ya trae la fecha de ingreso formateada (d/m/Y H:i).
            'fechaIngreso' => $view->fechaIngreso,
            'fechaEstimadaLlegada' => $fecha($view->fechaEstimadaLlegada, 'd/m/Y'),
            'bultos' => $bultos,
            'historial' => array_map(
                static fn (array $paso) => [
                    'estado' => $paso['estado'],
                    'estadoLabel' => $paso['estadoLabel'],
                    'fecha' => (new DateTimeImmutable($paso['fecha']))->format('d/m/Y H:i'),
                ],
                $view->historial,
            ),
        ];
    }
}
